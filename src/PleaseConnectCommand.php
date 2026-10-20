<?php

namespace Porro\Statamic;

use Porro\Laravel\Commands\ConnectCommand;

/**
 * Lets Statamic sites run `php please porro:connect <key>`.
 */
class PleaseConnectCommand extends ConnectCommand
{
    protected $signature = 'statamic:porro:connect {key : The site key from Porro} {--url= : Only if you run Porro somewhere other than app.porro.dev}';
}
