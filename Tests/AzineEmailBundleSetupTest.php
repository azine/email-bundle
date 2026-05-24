<?php

namespace Azine\EmailBundle\Tests;

/**
 * @author d.businger
 */
class AzineEmailBundleSetupTest extends \PHPUnit\Framework\TestCase
{
    public function testMagicQuotes()
    {
        if (!function_exists('get_magic_quotes_gpc')) {
            $this->assertTrue(true);

            return;
        }

        $this->assertFalse(get_magic_quotes_gpc(), 'magic_quotes_gpc should be turned off in php.ini');
    }
}
