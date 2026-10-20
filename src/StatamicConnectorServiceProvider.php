<?php

namespace Porro\Statamic;

use Illuminate\Support\ServiceProvider;

class StatamicConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([StatamicCollector::class], 'porro.collectors');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PleaseConnectCommand::class]);
        }
    }
}
