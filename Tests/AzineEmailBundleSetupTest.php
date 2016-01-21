<?php
namespace Azine\EmailBundle\Tests;

/**
 * @author d.businger
 */
class AzineEmailBundleSetupTest extends \PHPUnit_Framework_TestCase
{
    public function testMagicQuotes()
    {
        $this->assertEquals(0, get_magic_quotes_gpc(), "magic_quotes_gpc should be turned off in php.ini");
    }
}
