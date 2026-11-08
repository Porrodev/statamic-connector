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
        :root { color-scheme: light dark; --bg: #f6f6f4; --card: #fff; --text: #1c1c1a; --muted: #5d5d58; --line: #e2e2dd; --link: #1f5fbf; }
        @media (prefers-color-scheme: dark) { :root { --bg: #141413; --card: #1e1e1c; --text: #ededea; --muted: #a8a8a2; --line: #33332f; --link: #8ab4f8; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; background: var(--bg); color: var(--text); font: 16px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 440px; background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 28px; }
        h1 { margin: 0 0 10px; font-size: 20px; line-height: 1.3; }
        p { margin: 0; color: var(--muted); overflow-wrap: anywhere; }
        a { color: var(--link); }
        .actions { margin-top: 20px; font-size: 15px; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $message['title'] }}</h1>
        <p>{{ $message['body'] }}</p>
        @if ($login)
            <p class="actions"><a href="{{ $login }}">{{ __('Sign in with a password') }}</a></p>
        @endif
    </main>
</body>
</html>
