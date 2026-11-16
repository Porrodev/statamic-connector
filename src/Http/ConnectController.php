<?php

namespace Porro\Statamic\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Porro\Laravel\Connect\ConnectFailure;
use Porro\Laravel\Connect\Disconnect;
use Porro\Laravel\Connect\PanelConnect;
use Porro\Laravel\Reporting;
use Porro\Laravel\SiteKeys;
use Porro\Statamic\Panel;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * "Connect to Porro" on the Site Health page: start sends the browser to
 * Porro to approve, and the return route finishes it. Both need the
 * connect permission, not just viewing. Anything that goes wrong lands
 * back on Site Health with the reason in words.
 *
 * Disconnect undoes it, for the same people: Porro is told, and the key
 * is deleted from this site whatever Porro answered. Only ever on the
 * live site, and never a key from the environment.
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

    public function disconnect(Disconnect $disconnect, SiteKeys $keys): RedirectResponse
    {
        Panel::authorize(Panel::CONNECT);

        // A key from the environment isn't the control panel's to remove.
        if ($keys->source() === SiteKeys::ENV) {
            return redirect(cp_route('porro.index'))->with('error', __('To disconnect, remove PORRO_KEY from the environment and deploy. The control panel can’t change it.'));
        }

        // A staging or local copy of the live database holds the live
        // site's key: disconnecting here would switch the live site off.
        if (! Reporting::production()) {
            return redirect(cp_route('porro.index'))->with('error', __('This copy’s environment is “:environment”, not the live site, so it can’t be disconnected from here. Disconnect from the live site’s control panel.', ['environment' => app()->environment()]));
        }

        $result = $disconnect->disconnect();

        if ($result === null) {
            return redirect(cp_route('porro.index'))->with('error', __('This site isn’t connected to Porro.'));
        }

        // Site Health says what happened, on the next page view only.
        return redirect(cp_route('porro.index'))->with(Panel::DISCONNECTED, ['workspace' => $result['workspace'], 'told' => $result['told']]);
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
