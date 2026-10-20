<?php

namespace Porro\Statamic;

use Porro\Laravel\Commands\ConnectCommand;

/**
 * Lets Statamic sites run `php please porro:connect <key>`, with every
 * option the Laravel command has.
 */
class PleaseConnectCommand extends ConnectCommand
{
    public function __construct()
    {
        $this->signature = (string) preg_replace('/^porro:connect/', 'statamic:porro:connect', $this->signature);

        parent::__construct();
    }
}
