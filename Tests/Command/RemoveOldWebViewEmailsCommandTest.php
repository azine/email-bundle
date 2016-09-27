<?php
namespace Azine\EmailBundle\Tests\Command;

use Azine\EmailBundle\Tests\AzineQueryMock;
use Doctrine\ORM\EntityManager;
use Azine\EmailBundle\Command\RemoveOldWebViewEmailsCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

/**
 * @author dominik
 */
class RemoveOldWebViewEmailsCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testHelpInfo()
    {
        $application = new Application();
        $application->add(new RemoveOldWebViewEmailsCommand());

        $command = $application->find("emails:remove-old-web-view-emails");
        $this->assertContains("command deletes all SentEmail entities from the database", $command->getHelp());
        $this->assertContains('Remove all "SentEmail" from the database that are older than the configured time.', $command->getDescription());

    }

    /**
     * @expectedException \Exception
     */
    public function testDeleteSentEmailsFromWebViewNoConfig()
    {
        $application = new Application();
        $application->add(new RemoveOldWebViewEmailsCommand());

        $command = $application->find("emails:remove-old-web-view-emails");
        $days = null;
        $command->setContainer($this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock());

        $tester = new CommandTester($command);
        $tester->execute(array(''));
        $display = $tester->getDisplay();
        $this->assertContains('either the commandline parameter "keep" or the "azine_email_web_view_retention" in your config.yml or the default-config has to be defined.', $display);
    }

    public function testDeleteSentEmailsFromWebView()
    {
        $application = new Application();
        $application->add(new RemoveOldWebViewEmailsCommand());

        $command = $application->find("emails:remove-old-web-view-emails");
        $days = 66;
        $deletedWebMails = 9;
        $command->setContainer($this->getMockSetup($days, $deletedWebMails));

        $tester = new CommandTester($command);
        $tester->execute(array(''));
        $display = $tester->getDisplay();
        $this->assertContains("using the parameter from the configuration => '$days' days.", $display);
        $this->assertContains("$deletedWebMails SentEmails have been deleted that were older than", $display);
    }

    public function testDeleteSentEmailsFromWebViewWithDayParam()
    {
        $application = new Application();
        $application->add(new RemoveOldWebViewEmailsCommand());

        $command = $application->find("emails:remove-old-web-view-emails");
        $days = null;
        $deletedWebMails = 900;
        $command->setContainer($this->getMockSetup($days, $deletedWebMails, true));

        $tester = new CommandTester($command);
        $tester->execute(array('keep' => 121));
        $display = $tester->getDisplay();
        $this->assertContains("$deletedWebMails SentEmails have been deleted that were older than", $display);
        $this->assertTrue(strpos($display, "using the parameter from the configuration") === false, "display is:\n\n$display");
    }

    /**
     * @param integer|null $days
     * @param integer $deletedWebMails
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockSetup($days, $deletedWebMails, $useKeep = false)
    {
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();

        $queryBuilderMock = $this->getMockBuilder("Doctrine\ORM\QueryBuilder")->disableOriginalConstructor()->getMock();
        $queryBuilderMock->expects($this->once())->method("delete")->will($this->returnSelf());
        $queryBuilderMock->expects($this->once())->method("where")->will($this->returnSelf());
        $queryBuilderMock->expects($this->once())->method("setParameter")->will($this->returnSelf());
        $queryBuilderMock->expects($this->once())->method("getQuery")->will($this->returnValue(new AzineQueryMock($deletedWebMails)));

        $entityManagerMock = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();
        $entityManagerMock->expects($this->once())->method("createQueryBuilder")->will($this->returnValue($queryBuilderMock));

        $doctrineMock = $this->getMockBuilder("\Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
        $doctrineMock->expects($this->once())->method("getManager")->will($this->returnValue($entityManagerMock));

        if (!$useKeep) {
            $containerMock->expects($this->once())->method('getParameter')->with("azine_email_web_view_retention")->will($this->returnValue($days));
        }
        $containerMock->expects($this->once())->method('get')->with("doctrine")->will($this->returnValue($doctrineMock));

        return $containerMock;
    }
}
