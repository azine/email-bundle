<?php

namespace Azine\EmailBundle\Tests\Entity;

use Azine\EmailBundle\Entity\SentEmail;

class SentEmailTest extends \PHPUnit\Framework\TestCase
{
    public function testGetNewToken()
    {
        $tockens = array();
        while (sizeof($tockens) < 100) {
            $newToken = SentEmail::getNewToken();
            $this->assertNotContains($newToken, $tockens);
            $tockens[] = $newToken;
        }
    }
}
