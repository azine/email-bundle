<?php
namespace Azine\EmailBundle\Tests\Entity;

use Azine\EmailBundle\Entity\Notification;

class NotificationTest extends \PHPUnit_Framework_TestCase
{
    public function testSetCreated()
    {
        $n = new Notification();
        $c1 = $n->getCreated();

        $n->setCreatedValue();

        $c2 = $n->getCreated();

        sleep(1);

        $this->assertNull($c1);
        $this->assertNotNull($c2);
        $this->assertGreaterThanOrEqual($c2, new \DateTime());

    }
}
