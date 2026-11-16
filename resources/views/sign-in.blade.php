{{-- Shown when signing in from Porro doesn't work. A plain page of its own:
     no control panel scripts or styles, as the visitor isn't signed in. --}}
@php
    $messages = [
        'expired' => [
            'title' => __('This sign-in link has expired'),
            'body' => __('Links from Porro work once and only for 60 seconds. Go back to the site in Porro and choose Sign in again.'),
        ],
        'no_account' => [
            'title' => __('No control panel account for you here'),
            'body' => __('This site has no Statamic user with the email :email. Ask whoever manages the site to add you, then choose Sign in again in Porro.', ['email' => $email]),
        ],
        'ambiguous' => [
            'title' => __('More than one account uses this email'),
            'body' => __('More than one account on this site uses this email, so Porro can’t tell which to sign in. Ask a site admin to fix it.'),
        ],
        'switch' => [
            'title' => __('Switch accounts?'),
            'body' => __('You’re signed in as :current. Switch to :email?', ['current' => $current ?? '', 'email' => $email]),
        ],
        'switch_expired' => [
            'title' => __('This switch has expired'),
            'body' => __('Switching accounts has to be confirmed once, within five minutes, by whoever was signed in. Go back to the site in Porro and choose Sign in again.'),
        ],
        'no_cp_access' => [
            'title' => __('Your account can’t use the control panel'),
            'body' => __('The Statamic user :email doesn’t have the “Access the control panel” permission on this site. Ask whoever manages the site to give your role that permission.', ['email' => $email]),
        ],
        'turned_off_in_porro' => [
            'title' => __('Sign-in from Porro is turned off'),
            'body' => __('Signing in to client sites is turned off for this site in Porro. A workspace admin can turn it on in Porro’s settings.'),
        ],
        'turned_off_here' => [
            'title' => __('Sign-in from Porro is turned off'),
            'body' => __('This site has turned off signing in from Porro (PORRO_SIGN_IN=false). Sign in with your password instead.'),
        ],
        'not_live' => [
            'title' => __('Sign-in from Porro is off on this copy of the site'),
            'body' => __('Signing in from Porro works on the live site only, unless a copy turns it on (PORRO_SIGN_IN=true). Sign in with your password instead.'),
        ],
        'continue' => [
            'title' => __('Signing you in…'),
            'body' => __('Taking you to the control panel on :host. If nothing happens, choose Continue.', ['host' => request()->getHost()]),
        ],
        'not_connected' => [
            'title' => __('This site isn’t connected to Porro'),
            'body' => __('The site has no Porro site key, so it can’t check the link with Porro. Sign in with your password instead.'),
        ],
        'key_refused' => [
            'title' => __('Porro didn’t accept this site’s key'),
            'body' => __('The site key in this site’s .env doesn’t match the one in Porro. Run php please porro:connect with the key from the site page in Porro, then try again.'),
        ],
        'unreachable' => [
            'title' => __('Porro couldn’t be reached'),
            'body' => __('This site couldn’t check the link with Porro just now. Wait a moment, then go back to the site in Porro and choose Sign in again.'),
        ],
    ];

    $message = $messages[$reason] ?? $messages['unreachable'];
    $login = \Illuminate\Support\Facades\Route::has('statamic.cp.login') ? cp_route('login') : null;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $message['title'] }}</title>
    <style>
        /* Porro's own greys, so the page reads as Porro's without loading
           anything: the member has just come from Porro, and this page is
           on the client's site. Lime only in the dark, on the one button
           and the progress bar. */
        :root { color-scheme: light dark; --bg: #f6f6f4; --card: #fff; --text: #050505; --muted: #6b6e72; --faint: #9a9d9f; --line: #e3e3de; --primary: #0a0a0a; --on-primary: #fff; --track: #ececea; --point: #050505; }
        @media (prefers-color-scheme: dark) { :root { --bg: #050505; --card: #0a0a0a; --text: #f2f2f0; --muted: #9a9d9f; --faint: #80848a; --line: #1f2123; --primary: #c9f26a; --on-primary: #050505; --track: #1f2123; --point: #c9f26a; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; background: var(--bg); color: var(--text); font: 13px/1.4 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 420px; display: flex; flex-direction: column; gap: 14px; background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 28px; }
        h1 { margin: 0; font-size: 20px; line-height: 1.25; font-weight: 600; letter-spacing: -0.01em; }
        p { margin: 0; color: var(--muted); overflow-wrap: anywhere; }
        a { color: var(--text); text-underline-offset: 2px; }
        .from, .site { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--faint); }
        .from { display: flex; align-items: center; gap: 8px; font-size: 11px; letter-spacing: 0.06em; text-transform: uppercase; }
        .from svg { width: 16px; height: 16px; flex: none; color: var(--text); }
        .from circle { fill: var(--point); }
        .site { font-size: 12px; }
        .progress { height: 3px; border-radius: 2px; background: var(--track); overflow: hidden; }
        .progress span { display: block; width: 60%; height: 100%; border-radius: 2px; background: var(--primary); }
        /* A sweep, not a percentage: the page can't know how long Porro
           takes to answer. Still for anyone who asks for less motion. */
        @media (prefers-reduced-motion: no-preference) { .progress span { width: 40%; animation: porro-sweep 1.4s ease-in-out infinite; } }
        @keyframes porro-sweep { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
        .actions { display: flex; flex-wrap: wrap; gap: 12px 16px; align-items: center; }
        button { font: inherit; font-size: 14px; font-weight: 500; padding: 9px 14px; border-radius: 6px; border: 1px solid var(--primary); background: var(--primary); color: var(--on-primary); cursor: pointer; }
        button.quiet { border-color: var(--line); background: none; color: var(--text); }
    </style>
</head>
<body>
    <main>
        <p class="from">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 1.5 18.5 10 10 18.5 1.5 10Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="10" cy="10" r="2.5"/></svg>
            {{ __('Sign in from Porro') }}
        </p>
        <h1>{{ $message['title'] }}</h1>
        <p>{{ $message['body'] }}</p>
        @if ($reason === 'switch')
            <form method="POST" action="{{ route('statamic.porro.sign-in.confirm') }}" class="actions">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit">{{ __('Switch to :email', ['email' => $email]) }}</button>
                <a href="{{ cp_route('index') }}">{{ __('Stay as :current', ['current' => $current]) }}</a>
            </form>
        @elseif ($reason === 'continue')
            <div class="progress"><span></span></div>
            {{-- Posted on from this site's own page, so the browser sends
                 the session cookie it held back from Porro's. --}}
            <form method="POST" action="{{ route('statamic.porro.sign-in.continue') }}" class="actions" id="porro-continue">
                <input type="hidden" name="ticket" value="{{ $ticket }}">
                <button type="submit" class="quiet">{{ __('Continue') }}</button>
            </form>
            <script>document.getElementById('porro-continue').submit();</script>
        @elseif ($login)
            <p class="actions"><a href="{{ $login }}">{{ __('Sign in with a password') }}</a></p>
        @endif
        <p class="site">{{ request()->getHost() }} · {{ __('Porro connector') }}</p>
    </main>
</body>
</html>
