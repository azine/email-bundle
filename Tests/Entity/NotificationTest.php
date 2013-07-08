<?php
namespace Azine\EmailBundle\Tests\Entity;


use Azine\EmailBundle\Entity\Notification;

class NotificationTest extends \PHPUnit_Framework_TestCase
{
    public function testSetCreated(){
    	$n = new Notification();
    	$c1 = $n->getCreated();

    	$n->setCreatedValue();

    	$c2 = $n->getCreated();

    	$this->assertNull($c1);
    	$this->assertNotNull($c2);
    	$this->assertGreaterThanOrEqual(new \DateTime(), $c2);

    }
}
