<?php

namespace Porro\Statamic;

use Porro\Laravel\Commands\CronCommand;

/**
 * Lets Statamic sites run `php please porro:cron`.
 */
class PleaseCronCommand extends CronCommand
{
    protected $signature = 'statamic:porro:cron';
}
