<?php
namespace Azine\EmailBundle\Tests\Command;

use Azine\EmailBundle\Command\SendNewsLetterCommand;

use Symfony\Component\Console\Tester\CommandTester;

use Symfony\Component\Console\Application;

/**
 * @author dominik
 */
class SendNewsLetterCommandTest extends \PHPUnit_Framework_TestCase{

	public function testHelpInfo(){
		$application = new Application();
		$application->add(new SendNewsLetterCommand());

		$command = $application->find('emails:sendNewsletter');

		$display = $command->getHelp();
		$this->assertContains("Depending on you Swiftmailer-Configuration the email will be send directly or will be written to the spool.", $display);

	}

	public function testSend(){
		$application = new Application();
		$application->add(new SendNewsLetterCommand());

		$command = $application->find('emails:sendNewsletter');
		$command->setContainer($this->getMockSetup());

		$tester = new CommandTester($command);
		$tester->execute(array(''));
		$display = $tester->getDisplay();
		$this->assertContains(AzineNotifierServiceMock::EMAIL_COUNT." emails have been sent.", $display);

	}

	public function testSendFail(){
		$application = new Application();
		$application->add(new SendNewsLetterCommand());

		$command = $application->find('emails:sendNewsletter');
		$command->setContainer($this->getMockSetup(true));

		$tester = new CommandTester($command);
		$tester->execute(array(''));
		$display = $tester->getDisplay();
		$this->assertContains((AzineNotifierServiceMock::EMAIL_COUNT-1)." emails have been sent.", $display);
		$this->assertContains(AzineNotifierServiceMock::FAILED_ADDRESS, $display);

	}

	private function getMockSetup($fail = false){
		$containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();

		$notifierServiceMock = new AzineNotifierServiceMock($fail);

		$containerMock->expects($this->any())->method('get')->with('azine_email_notifier_service')->will($this->returnValue($notifierServiceMock));
		return $containerMock;
	}
}