<?php

use Porro\Statamic\Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * A valid site key, as Porro would issue it.
 */
function siteKey(): string
{
    return 'porro_01jabcdefghjkmnpqrstvwxyz0_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}
