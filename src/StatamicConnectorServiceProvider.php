<?php

namespace Porro\Statamic;

use Illuminate\Support\ServiceProvider;
use Porro\Statamic\Http\SiteHealthController;
use Statamic\Facades\Utility;
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

        // Utilities → Porro Site Health. Statamic adds an "Access Porro Site
        // Health utility" permission and checks it on the page and its routes;
        // super admins always have it.
        Utility::extend(function () {
            Utility::register('porro')
                ->title(__('Porro Site Health'))
                ->navTitle(__('Porro Site Health'))
                ->icon(Panel::ICON)
                ->description(__('Security advisories, end-of-life versions, updates and the scheduler. From Porro too, once connected.'))
                ->view('porro::utility', fn () => app(Panel::class)->data())
                ->routes(function ($router) {
                    $router->post('refresh', [SiteHealthController::class, 'refresh'])->name('refresh');
                });
        });

        SiteHealthWidget::register();
    }
}
