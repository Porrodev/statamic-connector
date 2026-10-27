<?php

namespace Porro\Statamic;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Porro\Laravel\Activity\Confirm;
use Porro\Statamic\Http\BlockingController;
use Porro\Statamic\Http\IpController;
use Porro\Statamic\Http\SiteHealthController;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Statamic;

class StatamicConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([StatamicCollector::class], 'porro.collectors');
        $this->app->tag([StatamicHealth::class], 'porro.health');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'porro');

        if ($this->app->runningInConsole()) {
            $this->commands([PleaseConnectCommand::class, PleaseCronCommand::class]);
        }

        // Its own "Porro" section in the nav, rather than tucked under
        // Utilities: Site Health and Blocking, each behind a permission.
        // Super admins always have both.
        Permission::extend(function () {
            Permission::group('porro', __('Porro'), function () {
                Permission::register(Panel::VIEW)->label(__('View Porro Site Health'));
                Permission::register(Panel::MANAGE)->label(__('Block and safelist IP addresses'));
            });
        });

        Nav::extend(function ($nav) {
            $nav->create(__('Site Health'))->section('Porro')->route('porro.index')->icon(Panel::ICON)->can(Panel::VIEW);
            $nav->create(__('Blocking'))->section('Porro')->route('porro.blocking')->icon(Panel::BLOCK_ICON)->can(Panel::VIEW);
        });

        Statamic::pushCpRoutes(function () {
            Route::prefix('porro')->name('porro.')->group(function () {
                Route::get('/', [SiteHealthController::class, 'index'])->name('index');
                Route::post('refresh', [SiteHealthController::class, 'refresh'])->name('refresh');
                Route::get('activity/{ip}', [IpController::class, 'show'])->name('ip')->where('ip', '[0-9A-Fa-f:.]+');
                Route::post('activity/{ip}/reputation', [IpController::class, 'reputation'])->name('reputation')->where('ip', '[0-9A-Fa-f:.]+');
                Route::get('blocking', [BlockingController::class, 'index'])->name('blocking');
                Route::get('blocking/nginx', [BlockingController::class, 'nginx'])->name('nginx');
                Route::post('blocking/block', [BlockingController::class, 'block'])->name('block');
                Route::post('blocking/unblock', [BlockingController::class, 'unblock'])->name('unblock');
                Route::post('blocking/safelist', [BlockingController::class, 'safelist'])->name('safelist');
                Route::post('blocking/unsafelist', [BlockingController::class, 'unsafelist'])->name('unsafelist');
            });

            // It used to be a utility: keep old bookmarks working.
            Route::get('utilities/porro', fn () => redirect(cp_route('porro.index')));
        });

        // Opens the block and safelist confirmation dialog. Vue drops script
        // tags from CP templates, so it comes in with the CP's own scripts.
        Statamic::inlineScript(Confirm::script());

        SiteHealthWidget::register();
    }
}
