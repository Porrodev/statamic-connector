<?php

namespace Porro\Statamic\Http;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Porro\Core\SiteKey;
use Porro\Statamic\SignIn;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\User;

/**
 * "Sign in to Statamic" from the site page in Porro. Porro's page posts a
 * one-time ticket here; the site checks it with Porro using its own key and
 * signs in the Statamic user with the member's email. No password changes
 * hands, no account is ever made and no role is ever changed: a member
 * without a control panel account here gets a page saying so. When someone
 * else is already signed in, it asks before switching.
 */
class SignInController extends Controller
{
    // A switch to another account must be confirmed within five minutes.
    private const SWITCH_CACHE = 'porro:sign-in:switch:';

    private const SWITCH_TTL = 300;

    public function __invoke(Request $request, SignIn $signIn): Response|RedirectResponse
    {
        // The site owner's switch comes first, and Porro is never asked.
        if (! SignIn::enabled()) {
            return $this->page('turned_off_here', 403);
        }

        try {
            $key = SiteKey::parse((string) config('porro.key'));
        } catch (InvalidArgumentException) {
            return $this->page('not_connected', 403);
        }

        // Only the shape Porro makes. Anything else can't be a live ticket,
        // so it isn't worth a request to Porro.
        $ticket = $request->input('ticket');

        if (! is_string($ticket) || ! preg_match('/^[A-Za-z0-9_.~:-]{16,512}$/', $ticket)) {
            return $this->page('expired', 410);
        }

        $result = $signIn->redeem($key, (string) config('porro.url'), $ticket);

        return match ($result['status']) {
            SignIn::OK => $this->signIn($request, (string) $result['email']),
            SignIn::EXPIRED => $this->page('expired', 410),
            SignIn::TURNED_OFF => $this->page('turned_off_in_porro', 403),
            SignIn::KEY_REFUSED => $this->page('key_refused', 502),
            default => $this->page('unreachable', 502),
        };
    }

    private function signIn(Request $request, string $email): Response|RedirectResponse
    {
        $users = $this->findUsers($email);

        if ($users->isEmpty()) {
            return $this->page('no_account', 403, $email);
        }

        // Two accounts whose emails differ only in case: picking one could
        // sign the member in as someone else, so neither is.
        if ($users->count() > 1) {
            return $this->page('ambiguous', 403, $email);
        }

        $user = $users->first();

        if (! $this->canUseCp($user)) {
            return $this->page('no_cp_access', 403, $email);
        }

        $current = $this->guard()->user();

        if ($current !== null && (string) $current->getAuthIdentifier() === (string) $user->id()) {
            return redirect(cp_route('index'));
        }

        // Someone else is signed in already. A ticket posted from another
        // site must never quietly swap who that is, so ask first.
        if ($current !== null) {
            return $this->confirmSwitch($current, $user);
        }

        return $this->logIn($request, $user);
    }

    /**
     * "Switch" on the confirmation page. A same-origin form by now, so it
     * goes through Laravel's CSRF check like any other.
     */
    public function confirm(Request $request): Response|RedirectResponse
    {
        if (! SignIn::enabled()) {
            return $this->page('turned_off_here', 403);
        }

        $claim = $this->readSwitchToken($request->input('token'));
        $current = $this->guard()->user();

        // Single use, under five minutes old, and only for the session it
        // was made for: whoever was signed in then must still be.
        if ($claim === null
            || ! Cache::pull(self::SWITCH_CACHE.$claim['nonce'])
            || $current === null
            || (string) $current->getAuthIdentifier() !== $claim['from']) {
            return $this->page('switch_expired', 410);
        }

        $user = User::find($claim['to']);

        // Checked again: the account may have changed in the meantime.
        if ($user === null || ! $this->canUseCp($user)) {
            return $this->page('no_cp_access', 403, $user?->email());
        }

        return $this->logIn($request, $user);
    }

    private function confirmSwitch(mixed $current, UserContract $user): Response
    {
        $nonce = Str::random(40);
        Cache::put(self::SWITCH_CACHE.$nonce, true, self::SWITCH_TTL);

        $token = Crypt::encryptString((string) json_encode([
            'nonce' => $nonce,
            'from' => (string) $current->getAuthIdentifier(),
            'to' => (string) $user->id(),
            'expires' => time() + self::SWITCH_TTL,
        ]));

        return $this->page('switch', 200, $user->email(), [
            'current' => (string) (User::fromUser($current)?->email() ?? ''),
            'token' => $token,
        ]);
    }

    /**
     * @return array{nonce: string, from: string, to: string}|null
     */
    private function readSwitchToken(mixed $token): ?array
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $claim = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($claim) || ! is_string($claim['nonce'] ?? null) || ! is_string($claim['from'] ?? null)
            || ! is_string($claim['to'] ?? null) || ! is_int($claim['expires'] ?? null) || $claim['expires'] < time()) {
            return null;
        }

        return ['nonce' => $claim['nonce'], 'from' => $claim['from'], 'to' => $claim['to']];
    }

    private function logIn(Request $request, UserContract $user): RedirectResponse
    {
        $this->guard()->login($user);

        // A fresh session id and CSRF token, so nothing from before the
        // sign-in carries over. The session isn't elevated: anything that
        // asks for the password again still does.
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect(cp_route('index'));
    }

    private function guard(): StatefulGuard
    {
        /** @var StatefulGuard */
        return Auth::guard(config('statamic.users.guards.cp') ?: null);
    }

    private function canUseCp(UserContract $user): bool
    {
        return $user->isSuper() || $user->hasPermission('access cp');
    }

    /**
     * Every user whose email matches, ignoring case. Statamic matches
     * emails exactly, so this asks for the exact and lowercase forms, and
     * uses "like" for the rest, then settles it with an exact comparison
     * (an underscore in "like" matches any character).
     *
     * @return Collection<int, UserContract>
     */
    private function findUsers(string $email): Collection
    {
        return collect([User::findByEmail($email), User::findByEmail(strtolower($email))])
            ->merge(User::query()->where('email', 'like', $email)->get())
            ->filter(fn ($user) => $user !== null && strcasecmp((string) $user->email(), $email) === 0)
            ->unique(fn ($user) => (string) $user->id())
            ->values();
    }

    /**
     * @param  array<string, string>  $data
     */
    private function page(string $reason, int $status, ?string $email = null, array $data = []): Response
    {
        return response()
            ->view('porro::sign-in', ['reason' => $reason, 'email' => $email] + $data, $status)
            // A sign-in answer is for this visitor once, never for a cache.
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
