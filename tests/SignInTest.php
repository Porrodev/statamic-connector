<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Porro\Core\SiteKey;
use Statamic\Facades\Role;
use Statamic\Facades\User;

const TICKET = 'tkt_7Hq2mV9xR4pL8sN3wK6yB1cD5fG0jZ';

beforeEach(function () {
    // Users are files that outlive a test; each test starts with none.
    User::all()->each->delete();

    $this->key = siteKey();
    // Tests aren't the live site, so sign-in is turned on by hand.
    config(['porro.key' => $this->key, 'porro.sign_in' => true]);

    // As the site's own page posts the ticket on to the second step.
    $this->withHeader('Sec-Fetch-Site', 'same-origin');
});

// What a browser sends with a post from Porro's page.
const FROM_PORRO = ['Origin' => 'https://porro.test'];

// Where Porro's page posts the ticket, from another site.
function startUrl(): string
{
    return '/!/porro/sign-in';
}

// Where this site's own page posts it on.
function signInUrl(): string
{
    return '/!/porro/sign-in/continue';
}

function porroAnswers(int $status, array $body): void
{
    Http::fake(['porro.test/api/v1/sign-in/redeem' => Http::response($body, $status)]);
}

function editorWithCp(string $email = 'sam@example.com')
{
    $user = tap(User::make()->email($email))->save();
    $role = tap(Role::make('editor')->addPermission('access cp'))->save();

    return tap($user->assignRole($role))->save();
}

/**
 * The public half of a site key, as Porro stores it.
 */
function publicKey(string $token): string
{
    preg_match('/_([A-Za-z0-9_-]{43})$/', $token, $m);

    return sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(base64_decode(strtr($m[1], '-_', '+/'))));
}

it('lives at /!/porro/sign-in', function () {
    expect(route('statamic.porro.sign-in', absolute: false))->toBe(startUrl())
        ->and(route('statamic.porro.sign-in.continue', absolute: false))->toBe(signInUrl());
});

it('starts without a session, and hands the ticket on from this site’s own page', function () {
    Http::fake();
    $user = editorWithCp();
    $route = Route::getRoutes()->getByName('statamic.porro.sign-in');

    // Posted from Porro's page, so the browser holds the session cookie back.
    $response = $this->withHeader('Sec-Fetch-Site', 'cross-site')->post(startUrl(), ['ticket' => TICKET], FROM_PORRO)
        ->assertOk()
        ->assertSee('action="'.route('statamic.porro.sign-in.continue').'"', false)
        ->assertSee('name="ticket" value="'.TICKET.'"', false)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");

    // No session started: a new one would replace whoever is signed in.
    expect($response->headers->getCookies())->toBe([])
        ->and(app('router')->gatherRouteMiddleware($route))->toBe(['Illuminate\Routing\Middleware\ThrottleRequests:10,1,porro-sign-in-start'])
        ->and(Auth::check())->toBeFalse();
    Http::assertNothingSent();
});

it('starts nothing for something that isn’t a ticket, or a ticket in the address', function () {
    $this->post(startUrl(), ['ticket' => 'abc'], FROM_PORRO)->assertStatus(410)->assertDontSee('name="ticket"', false);
    $this->post(startUrl().'?ticket='.TICKET, [], FROM_PORRO)->assertStatus(410)->assertDontSee(TICKET);
    $this->post(startUrl().'?ticket='.TICKET, ['ticket' => TICKET], FROM_PORRO)->assertStatus(410)->assertDontSee(TICKET);
});

it('only starts for a ticket posted from Porro’s own page', function (array $headers) {
    // Any other site could post a ticket of its own, to have this site's
    // page sign a visitor in to its account.
    $this->post(startUrl(), ['ticket' => TICKET], $headers)
        ->assertStatus(410)
        ->assertDontSee(TICKET);
})->with([
    'another site' => [['Origin' => 'https://evil.example']],
    'a page that hides its origin' => [['Origin' => 'null']],
    'no origin at all' => [[]],
    'Porro’s host over plain http' => [['Origin' => 'http://porro.test']],
    'Porro’s host on another port' => [['Origin' => 'https://porro.test:8443']],
    'a host that starts like Porro’s' => [['Origin' => 'https://porro.test.evil.example']],
]);

it('only signs in from this site’s own page', function (array $headers, bool $allowed) {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    editorWithCp();

    $response = $this->flushHeaders()->post(signInUrl(), ['ticket' => TICKET], $headers);

    if ($allowed) {
        $response->assertRedirect(cp_route('index'));

        return;
    }

    // Turned away before Porro is asked, so the ticket isn't spent.
    $response->assertStatus(410);
    Http::assertNothingSent();
    expect(Auth::check())->toBeFalse();
})->with([
    'another site' => [['Sec-Fetch-Site' => 'cross-site'], false],
    'another site, same registrable domain' => [['Sec-Fetch-Site' => 'same-site'], false],
    'another site claiming this origin' => [['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'http://localhost'], false],
    'no word on where it came from' => [[], false],
    'an older browser, another origin' => [['Origin' => 'https://evil.example'], false],
    'an older browser, this host on another port' => [['Origin' => 'http://localhost:8080'], false],
    'an older browser, this host over https' => [['Origin' => 'https://localhost'], false],
    'an older browser, this origin' => [['Origin' => 'http://localhost'], true],
]);

it('won’t take a ticket from the address', function () {
    Http::fake();

    $this->post(signInUrl().'?ticket='.TICKET)->assertStatus(410);
    $this->post(signInUrl().'?ticket='.TICKET, ['ticket' => TICKET])->assertStatus(410);

    Http::assertNothingSent();
});

it('is off on a copy of the site that isn’t live, unless the copy turns it on', function () {
    Http::fake();
    config(['porro.sign_in' => null]);

    $this->post(startUrl(), ['ticket' => TICKET], FROM_PORRO)->assertForbidden()->assertSee('works on the live site only');
    $this->post(signInUrl(), ['ticket' => TICKET])->assertForbidden()->assertSee('works on the live site only');
    Http::assertNothingSent();

    $this->app['env'] = 'production';
    $this->post(startUrl(), ['ticket' => TICKET], FROM_PORRO)->assertOk();

    $this->app['env'] = 'staging';
    config(['porro.sign_in' => 'true']);
    $this->post(startUrl(), ['ticket' => TICKET], FROM_PORRO)->assertOk();
});

it('signs the member in to the control panel and sends them to the dashboard', function () {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    $user = editorWithCp();

    $this->post(signInUrl(), ['ticket' => TICKET])->assertRedirect(cp_route('index'));

    expect(Auth::guard(config('statamic.users.guards.cp'))->id())->toBe($user->id());
});

it('matches the email whatever its case', function (string $fromPorro, string $here) {
    porroAnswers(200, ['ok' => true, 'email' => $fromPorro, 'name' => 'Sam Patel']);
    $user = editorWithCp($here);
    tap(User::make()->email('sam@examplexcom'))->save();

    $this->post(signInUrl(), ['ticket' => TICKET])->assertRedirect(cp_route('index'));

    expect(Auth::id())->toBe($user->id());
})->with([
    'capitals in Porro' => ['Sam@example.COM', 'sam@example.com'],
    'capitals here' => ['sam@example.com', 'Sam@example.COM'],
    'capitals in both' => ['sam@EXAMPLE.com', 'Sam@example.COM'],
    'an underscore' => ['Sam_Patel@example.com', 'sam_patel@example.com'],
]);

it('lets super admins in without a role', function () {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    $user = tap(User::make()->email('sam@example.com')->makeSuper())->save();

    $this->post(signInUrl(), ['ticket' => TICKET])->assertRedirect(cp_route('index'));

    expect(Auth::id())->toBe($user->id());
});

it('redeems the ticket with a request signed by the site key', function () {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    editorWithCp();

    $this->post(signInUrl(), ['ticket' => TICKET]);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $headers = fn (string $name) => $request->header($name)[0] ?? '';
        $message = SiteKey::message('POST', '/api/v1/sign-in/redeem', $headers('X-Porro-Timestamp'), $headers('X-Porro-Nonce'), $request->body());

        expect($request->method())->toBe('POST')
            ->and($request->url())->toBe('https://porro.test/api/v1/sign-in/redeem')
            ->and(json_decode($request->body(), true))->toBe(['ticket' => TICKET])
            ->and($headers('Content-Type'))->toStartWith('application/json')
            ->and($headers('X-Porro-Site'))->toBe(SiteKey::parse($this->key)->siteId)
            ->and(abs((int) $headers('X-Porro-Timestamp') - time()))->toBeLessThan(5)
            ->and($headers('X-Porro-Nonce'))->not->toBe('')
            ->and(sodium_crypto_sign_verify_detached(base64_decode($headers('X-Porro-Signature')), $message, publicKey($this->key)))->toBeTrue()
            // Bound to this endpoint: it wouldn't pass as a report.
            ->and(sodium_crypto_sign_verify_detached(base64_decode($headers('X-Porro-Signature')), SiteKey::message('POST', '/api/v1/reports', $headers('X-Porro-Timestamp'), $headers('X-Porro-Nonce'), $request->body()), publicKey($this->key)))->toBeFalse();

        return true;
    });
});

it('says the link has expired when Porro has already used it, and signs no one in', function () {
    porroAnswers(410, ['ok' => false, 'error' => 'That sign-in link has expired.']);
    editorWithCp();

    $this->post(signInUrl(), ['ticket' => TICKET])
        ->assertStatus(410)
        ->assertSee('Links from Porro work once and only for 60 seconds. Go back to the site in Porro and choose Sign in again.')
        ->assertDontSee(TICKET)
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(Auth::check())->toBeFalse();
});

it('never asks Porro about something that isn’t a ticket', function (mixed $ticket) {
    Http::fake();

    $this->post(signInUrl(), ['ticket' => $ticket])->assertStatus(410);

    Http::assertNothingSent();
})->with([
    'missing' => [null],
    'too short' => ['abc'],
    'not a string' => [['a' => 'b']],
    'odd characters' => ['<script>alert(1)</script>xxxxxxxx'],
]);

it('says so when there’s no Statamic user with the member’s email', function () {
    porroAnswers(200, ['ok' => true, 'email' => 'someone@agency.test', 'name' => 'Someone']);
    editorWithCp();

    $this->post(signInUrl(), ['ticket' => TICKET])
        ->assertForbidden()
        ->assertSee('No control panel account for you here')
        ->assertSee('someone@agency.test');

    expect(Auth::check())->toBeFalse()
        ->and(User::findByEmail('someone@agency.test'))->toBeNull();
});

it('won’t sign in a user who can’t use the control panel', function () {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    $user = tap(User::make()->email('sam@example.com'))->save();

    $this->post(signInUrl(), ['ticket' => TICKET])
        ->assertForbidden()
        ->assertSee('Your account can’t use the control panel', false);

    expect(Auth::check())->toBeFalse()
        ->and($user->fresh()->roles())->toBeEmpty()
        ->and($user->fresh()->isSuper())->toBeFalse();
});

it('says sign-in is off when Porro has it turned off for the site', function () {
    porroAnswers(403, ['ok' => false, 'error' => 'Sign-in is turned off for this site.']);
    editorWithCp();

    $this->post(signInUrl(), ['ticket' => TICKET])
        ->assertForbidden()
        ->assertSee('Signing in to client sites is turned off for this site in Porro.');

    expect(Auth::check())->toBeFalse();
});

it('is turned off by PORRO_SIGN_IN=false without asking Porro', function () {
    config(['porro.sign_in' => 'false']);
    Http::fake();
    editorWithCp();

    $this->post(signInUrl(), ['ticket' => TICKET])
        ->assertForbidden()
        ->assertSee('This site has turned off signing in from Porro');

    Http::assertNothingSent();
    expect(Auth::check())->toBeFalse();
});

it('says Porro couldn’t be reached when it can’t be', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28 while posting '.TICKET));
    editorWithCp();

    $this->post(signInUrl(), ['ticket' => TICKET])
        ->assertStatus(502)
        ->assertSee('Porro couldn’t be reached', false)
        ->assertDontSee('cURL')
        ->assertDontSee(TICKET);

    expect(Auth::check())->toBeFalse();
});

it('treats a Porro error or an odd answer as Porro being unreachable', function (int $status, array $body) {
    porroAnswers($status, $body);
    editorWithCp();

    $this->post(signInUrl(), ['ticket' => TICKET])->assertStatus(502)->assertSee('Porro couldn’t be reached', false);

    expect(Auth::check())->toBeFalse();
})->with([
    'server error' => [500, ['message' => 'Server Error']],
    'ok without an email' => [200, ['ok' => true]],
    'not ok' => [200, ['ok' => false, 'email' => 'sam@example.com']],
]);

it('says the site key was refused when Porro rejects the signature', function () {
    porroAnswers(401, ['ok' => false, 'error' => 'Bad signature.']);
    editorWithCp();

    $this->post(signInUrl(), ['ticket' => TICKET])->assertStatus(502)->assertSee('Porro didn’t accept this site’s key', false);

    expect(Auth::check())->toBeFalse();
});

it('says the site isn’t connected when it has no site key', function () {
    config(['porro.key' => null]);
    Http::fake();

    $this->post(signInUrl(), ['ticket' => TICKET])->assertForbidden()->assertSee('This site isn’t connected to Porro', false);

    Http::assertNothingSent();
});

it('takes the ticket without a CSRF token, as it comes from Porro', function () {
    $route = Route::getRoutes()->getByName('statamic.porro.sign-in.continue');
    $middleware = app('router')->gatherRouteMiddleware($route);

    // Not the class Laravel ships, nor an app's own subclass of it.
    $checksCsrf = collect($middleware)->contains(fn ($class) => is_string($class) && collect([ValidateCsrfToken::class, VerifyCsrfToken::class, PreventRequestForgery::class])
        ->contains(fn (string $csrf) => is_a($class, $csrf, true)));

    expect($route->methods())->toBe(['POST'])
        ->and($checksCsrf)->toBeFalse()
        ->and($middleware)->toContain('Illuminate\Routing\Middleware\ThrottleRequests:10,1,porro-sign-in');
});

it('is limited to 10 tries a minute from one IP', function () {
    porroAnswers(410, ['ok' => false, 'error' => 'expired']);

    foreach (range(1, 10) as $try) {
        $this->post(signInUrl(), ['ticket' => TICKET])->assertStatus(410);
    }

    $this->post(signInUrl(), ['ticket' => TICKET])->assertStatus(429);
    Http::assertSentCount(10);
});

it('isn’t turned away for a missing CSRF token outside tests', function () {
    porroAnswers(410, ['ok' => false, 'error' => 'expired']);
    // Laravel skips CSRF checks while running tests; pretend it's live.
    $this->app['env'] = 'production';

    $this->post(signInUrl(), ['ticket' => TICKET])->assertStatus(410);
});

it('signs no one in when two accounts share the email in different cases', function () {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    editorWithCp('Sam@example.com');
    tap(User::make()->email('sam@example.COM')->makeSuper())->save();

    $this->post(signInUrl(), ['ticket' => TICKET])
        ->assertForbidden()
        ->assertSee('More than one account on this site uses this email, so Porro can’t tell which to sign in. Ask a site admin to fix it.', false);

    expect(Auth::check())->toBeFalse();
});

function switchToken(string $html): string
{
    preg_match('/name="token" value="([^"]+)"/', $html, $m);

    return html_entity_decode($m[1] ?? '');
}

it('goes straight to the control panel when the member is already signed in as that user', function () {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    $user = editorWithCp();

    $this->actingAs($user)->post(signInUrl(), ['ticket' => TICKET])->assertRedirect(cp_route('index'));

    expect(Auth::id())->toBe($user->id());
});

it('asks before switching away from someone else who’s signed in', function () {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    $member = editorWithCp();
    $other = tap(User::make()->email('client@example.com')->makeSuper())->save();

    $page = $this->actingAs($other)->post(signInUrl(), ['ticket' => TICKET])
        ->assertOk()
        ->assertSee('You’re signed in as client@example.com. Switch to sam@example.com?', false)
        ->assertSee(route('statamic.porro.sign-in.confirm'))
        ->assertDontSee(TICKET)
        ->assertHeader('Cache-Control', 'no-store, private')
        // Never in another site's frame, with "Switch" dressed up as something else.
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");

    // The ticket is spent, but no one has been swapped yet.
    Http::assertSentCount(1);
    expect(Auth::id())->toBe($other->id());

    $token = switchToken($page->getContent());
    expect($token)->not->toBe('');

    $this->post(route('statamic.porro.sign-in.confirm'), ['token' => $token])->assertRedirect(cp_route('index'));
    expect(Auth::id())->toBe($member->id());
});

it('takes a switch token once', function () {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    editorWithCp();
    $other = tap(User::make()->email('client@example.com')->makeSuper())->save();

    $token = switchToken($this->actingAs($other)->post(signInUrl(), ['ticket' => TICKET])->getContent());
    $this->post(route('statamic.porro.sign-in.confirm'), ['token' => $token])->assertRedirect(cp_route('index'));

    $this->actingAs($other)->post(route('statamic.porro.sign-in.confirm'), ['token' => $token])->assertStatus(410);
    expect(Auth::id())->toBe($other->id());
});

it('refuses a switch token that’s old, altered or for someone else’s session', function (string $case) {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    editorWithCp();
    $other = tap(User::make()->email('client@example.com')->makeSuper())->save();
    $third = tap(User::make()->email('third@example.com')->makeSuper())->save();

    $token = switchToken($this->actingAs($other)->post(signInUrl(), ['ticket' => TICKET])->getContent());
    $as = $other;

    match ($case) {
        'old' => $this->travel(6)->minutes(),
        'altered' => $token = substr($token, 0, -4).'AAAA',
        'another session' => $as = $third,
    };

    $this->actingAs($as)->post(route('statamic.porro.sign-in.confirm'), ['token' => $token])
        ->assertStatus(410)
        ->assertSee('This switch has expired');

    expect(Auth::id())->toBe($as->id());
})->with(['old', 'altered', 'another session']);

it('refuses a switch with no one signed in', function () {
    porroAnswers(200, ['ok' => true, 'email' => 'sam@example.com', 'name' => 'Sam Patel']);
    editorWithCp();
    $other = tap(User::make()->email('client@example.com')->makeSuper())->save();

    $token = switchToken($this->actingAs($other)->post(signInUrl(), ['ticket' => TICKET])->getContent());
    Auth::logout();

    $this->post(route('statamic.porro.sign-in.confirm'), ['token' => $token])->assertStatus(410);
    expect(Auth::check())->toBeFalse();
});

it('checks CSRF on the switch, as it’s posted from the site itself', function () {
    $route = Route::getRoutes()->getByName('statamic.porro.sign-in.confirm');
    $middleware = app('router')->gatherRouteMiddleware($route);

    // Laravel 10's web group checks with a subclass of VerifyCsrfToken.
    $checksCsrf = collect($middleware)->contains(fn ($class) => is_string($class) && collect([ValidateCsrfToken::class, VerifyCsrfToken::class, PreventRequestForgery::class])
        ->contains(fn (string $csrf) => is_a($class, $csrf, true)));

    expect($route->methods())->toBe(['POST'])
        ->and($checksCsrf)->toBeTrue();

    // Laravel 13 lets a same-origin post through on the browser's word;
    // without it, only the token will do.
    $this->app['env'] = 'production';
    $this->flushHeaders()->post(route('statamic.porro.sign-in.confirm'), ['token' => 'x'])->assertStatus(419);
});
