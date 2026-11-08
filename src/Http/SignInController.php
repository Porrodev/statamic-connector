<?php

namespace Porro\Statamic\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
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
 * without a control panel account here gets a page saying so.
 */
class SignInController extends Controller
{
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
        $user = $this->findUser($email);

        if ($user === null) {
            return $this->page('no_account', 403, $email);
        }

        if (! $user->isSuper() && ! $user->hasPermission('access cp')) {
            return $this->page('no_cp_access', 403, $email);
        }

        Auth::guard(config('statamic.users.guards.cp') ?: null)->login($user);

        // A fresh session id and CSRF token, so nothing from before the
        // sign-in carries over. The session isn't elevated: anything that
        // asks for the password again still does.
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect(cp_route('index'));
    }

    /**
     * Statamic matches emails exactly, and Porro's may differ in case from
     * the one on the account here.
     */
    private function findUser(string $email): ?UserContract
    {
        $user = User::findByEmail($email) ?? User::findByEmail(strtolower($email));

        if ($user !== null) {
            return $user;
        }

        // "like" narrows it down; the exact check below settles it, as an
        // underscore in the address matches any character there.
        return User::query()->where('email', 'like', $email)->get()
            ->first(fn ($candidate) => strcasecmp((string) $candidate->email(), $email) === 0);
    }

    private function page(string $reason, int $status, ?string $email = null): Response
    {
        return response()
            ->view('porro::sign-in', ['reason' => $reason, 'email' => $email], $status)
            // A sign-in answer is for this visitor once, never for a cache.
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
