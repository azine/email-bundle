<?php
namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\Tests\AzineQueryMock;
use Doctrine\DBAL\LockMode;
use Azine\EmailBundle\Services\AzineRecipientProvider;

class AzineRecipientProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetRecipient()
    {
        $id = 11;

        $recipientMock = $this->getMockBuilder("Azine\EmailBundle\Entity\RecipientInterface")->disableOriginalConstructor()->getMock();
        $recipientMock->expects($this->once())->method("getId")->will($this->returnValue($id));

        $repositoryMock = $this->getMockBuilder("Doctrine\ORM\EntityRepository")->disableOriginalConstructor()->getMock();
        $repositoryMock->expects($this->once())->method("find")->with($id, LockMode::NONE, null)->will($this->returnValue($recipientMock));

        $entityManagerMock = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();
        $entityManagerMock->expects($this->once())->method("getRepository")->will($this->returnValue($repositoryMock));

        $managerRegistryMock = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
        $managerRegistryMock->expects($this->any())->method("getManager")->will($this->returnValue($entityManagerMock));

        $recipientProvider = new AzineRecipientProvider($managerRegistryMock, 'a-user-class', 'newsletterField');

        $recipient = $recipientProvider->getRecipient($id);
        $this->assertEquals($id, $recipient->getId());
    }

    public function testGetNewsletterRecipientIDs()
    {
        $queryResult = array(array('id' => 11),array('id' => 12),array('id' => 13),array('id' => 14));
        $recipientsArray = array(11,12,13,14);

        $queryBuilderMock = $this->getMockBuilder("Doctrine\ORM\QueryBuilder")->disableOriginalConstructor()->getMock();
        $queryBuilderMock->expects($this->once())->method("select")->will($this->returnSelf());
        $queryBuilderMock->expects($this->once())->method("from")->will($this->returnSelf());
        $queryBuilderMock->expects($this->once())->method("where")->will($this->returnSelf());
        $queryBuilderMock->expects($this->exactly(2))->method("andWhere")->will($this->returnSelf());

        $queryBuilderMock->expects($this->once())->method("getQuery")->will($this->returnValue(new AzineQueryMock($queryResult)));

        $entityManagerMock = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();
        $entityManagerMock->expects($this->once())->method("createQueryBuilder")->will($this->returnValue($queryBuilderMock));

        $managerRegistryMock = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
        $managerRegistryMock->expects($this->any())->method("getManager")->will($this->returnValue($entityManagerMock));

        $recipientProvider = new AzineRecipientProvider($managerRegistryMock, 'a-user-class', 'newsletterField');
        $recipients = $recipientProvider->getNewsletterRecipientIDs();
        $this->assertEquals($recipientsArray, $recipients);
    }
}
