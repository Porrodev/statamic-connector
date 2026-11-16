<?php

namespace Porro\Statamic;

use Porro\Laravel\Commands\DisconnectCommand;

/**
 * Lets Statamic sites run `php please porro:disconnect`, with every
 * option the Laravel command has.
 */
class PleaseDisconnectCommand extends DisconnectCommand
{
    public function __construct()
    {
        $this->signature = (string) preg_replace('/^porro:disconnect/', 'statamic:porro:disconnect', $this->signature);

        parent::__construct();
    }
}
