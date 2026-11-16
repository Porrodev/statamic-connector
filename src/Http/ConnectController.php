<?php

namespace Porro\Statamic\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Porro\Laravel\Connect\ConnectFailure;
use Porro\Laravel\Connect\PanelConnect;
use Porro\Statamic\Panel;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * "Connect to Porro" on the Site Health page: start sends the browser to
 * Porro to approve, and the return route finishes it. Both need the
 * connect permission, not just viewing. Anything that goes wrong lands
 * back on Site Health with the reason in words.
 */
class ConnectController extends CpController
{
    public function start(Request $request, PanelConnect $connect): RedirectResponse
    {
        Panel::authorize(Panel::CONNECT);

        try {
            return redirect()->away($connect->start($request, self::returnPath(), self::user()));
        } catch (ConnectFailure $e) {
            return redirect(cp_route('porro.index'))->with('error', $e->getMessage());
        }
    }

    public function return(Request $request, PanelConnect $connect): RedirectResponse
    {
        Panel::authorize(Panel::CONNECT);

        // Porro's own page sends the person back here when they're done.
        if (! $connect->returning($request)) {
            return redirect(cp_route('porro.index'));
        }

        try {
            $next = $connect->finish($request, self::user());
        } catch (ConnectFailure $e) {
            return redirect(cp_route('porro.index'))->with('error', $e->getMessage());
        }

        return $next !== null
            ? redirect()->away($next)
            : redirect(cp_route('porro.index'))->with('success', __('Connected to Porro. The first report has been sent.'));
    }

    /**
     * The return route as a path: the address comes from the host the
     * browser is on, not from APP_URL.
     */
    private static function returnPath(): string
    {
        return (string) parse_url(cp_route('porro.connect.return'), PHP_URL_PATH);
    }

    /**
     * Whoever is signed in. Panel::authorize() has already turned away
     * anyone who isn't; a request to connect is never tied to nobody.
     */
    private static function user(): string
    {
        $id = (string) User::current()?->id();

        abort_if($id === '', 403);

        return $id;
    }
}
