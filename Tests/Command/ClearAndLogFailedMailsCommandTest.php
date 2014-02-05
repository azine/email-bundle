<?php
namespace Azine\EmailBundle\Tests\Command;

use Symfony\Component\Finder\Finder;

use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Azine\EmailBundle\Command\ClearAndLogFailedMailsCommand;

use Symfony\Component\Console\Tester\CommandTester;

use Symfony\Component\Console\Application;

/**
 * @author dominik
 */
class ClearAndLogFailedMailsCommandTest extends \PHPUnit_Framework_TestCase{


	public function testHelpInfo(){
		$application = new Application();
		$application->add(new ClearAndLogFailedMailsCommand());

		$command = $application->find('emails:clear-and-log-failures');

		$display = $command->getHelp();
		$this->assertContains("Any email-address that still failed, is logged.", $display);
	}


	public function testSendingFailedMails(){
		$application = new Application();
		$application->add(new ClearAndLogFailedMailsCommand());

		$command = $application->find('emails:clear-and-log-failures');
		$message = "sfdsf";
		$failedRecipients = array('failed@email.com');
		$count = 2;

		$this->createFakeFailedMessageFiles($count);
		$command->setContainer($this->getMockSetup($message, $failedRecipients, false, false, $this->exactly($count)));

		$tester = new CommandTester($command);
		$tester->execute(array(''));
		$display = $tester->getDisplay();
		$this->assertContains("Retrying to send 'subject blabbla' to 'test-recipient@test.com'", $display);
		$this->assertContains("Sent!", $display);
	}

	public function testSendingFailedMailsWithDate(){
		$application = new Application();
		$application->add(new ClearAndLogFailedMailsCommand());

		$command = $application->find('emails:clear-and-log-failures');
		$message = "sfdsf";
		$failedRecipients = array('failed@email.com');
		$count = 2;
		$this->createFakeFailedMessageFiles($count);

		$command->setContainer($this->getMockSetup($message, $failedRecipients, false, false, $this->exactly($count)));

		$tester = new CommandTester($command);
		$tester->execute(array('date' => 'now'));
		$display = $tester->getDisplay();
		$this->assertContains("Retrying to send 'subject blabbla' to 'test-recipient@test.com'", $display);
		$this->assertContains("Sent!", $display);
	}

	public function testSendingFailedMailsNoMailsFound(){
		$application = new Application();
		$application->add(new ClearAndLogFailedMailsCommand());

		$command = $application->find('emails:clear-and-log-failures');
		$message = "sfdsf";
		$failedRecipients = array('failed@email.com');
		$command->setContainer($this->getMockSetup($message, $failedRecipients, false, false, $this->never()));

		$tester = new CommandTester($command);
		$tester->execute(array(''));
		$display = $tester->getDisplay();
		$this->assertContains("No failed-message-files found", $display);
	}


	public function testSendingFailedMailsWithoutTransport(){
		$application = new Application();
		$application->add(new ClearAndLogFailedMailsCommand());

		$command = $application->find('emails:clear-and-log-failures');
		$message = "sfdsf";
		$failedRecipients = array('failed@email.com');
		$command->setContainer($this->getMockSetup($message, $failedRecipients, false, true));

		$tester = new CommandTester($command);
		$tester->execute(array(''));
		$display = $tester->getDisplay();
		$this->assertContains("Could not load transport. Is file-spooling configured in your config.yml for this environment?", $display);
	}

	public function testSendingFailedMailsWithoutSpooling(){
		$application = new Application();
		$application->add(new ClearAndLogFailedMailsCommand());

		$command = $application->find('emails:clear-and-log-failures');
		$message = "sfdsf";
		$failedRecipients = array('failed@email.com');
		$command->setContainer($this->getMockSetup($message, $failedRecipients, true));

		$tester = new CommandTester($command);
		$tester->execute(array(''));
		$display = $tester->getDisplay();
		$this->assertContains("Could not find file spool path. Is file-spooling configured in your config.yml for this environment?", $display);
	}

	private function getMockSetup($message, $failedRecipients, $noSpoolPath = false, $noTransport = false, $msgCount = null){

		if($msgCount == null){
			$msgCount = $this->once();
		}

		$containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();


		$loggerMock = $this->getMockBuilder("Psr\Log\LoggerInterface")->disableOriginalConstructor()->getMock();
		$loggerMock->expects($this->any())->method("warning")->with("<error>Failed to send an email to : ".implode(", ", $failedRecipients)."</error>");


		if ($noTransport){
			$containerMock->expects($this->once())->method('get')->will($this->throwException(new ServiceNotFoundException('swiftmailer.transport.real')));
			return $containerMock;

		}
		$transportMock = $this->getMockBuilder("\Swift_SmtpTransport")->getMock();

		if($noSpoolPath){
			$containerMock->expects($this->once())->method('get')->will($this->returnValue($transportMock));
			$containerMock->expects($this->once())->method('getParameter')->will($this->throwException(new InvalidArgumentException()));
			return $containerMock;
		}
		$transportMock->expects($this->once())->method("isStarted")->will($this->returnValue(false));
		$transportMock->expects($this->once())->method("start");
		$transportMock->expects($msgCount)->method("send");

		$containerMock->expects($this->once())->method('get')->will($this->returnValueMap(array(array('swiftmailer.transport.real', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $transportMock))));
		$containerMock->expects($this->exactly(2))->method('getParameter')->will($this->returnValueMap(array(
																									array('swiftmailer.mailers', array('default_mailer' => 'a dummy value for a mailer')),
																									array('swiftmailer.spool.default_mailer.file.path', __DIR__."/mock.spool.path"))
																				));

		return $containerMock;
	}

	private function createFakeFailedMessageFiles($count = 1){
		while ($count > 0) {
			$random = md5(date('now')).$count.rand(0, 10000000);
			$filename = __DIR__."/mock.spool.path/$random.sending";
			$msg = new \Swift_Message();
			$msg->setTo("test-recipient@test.com");
			$msg->setBody("random file $random bla bla.");
			$msg->setSubject("subject blabbla");
			$msg->setSender("test@test.com");
			$ser = serialize($msg);
			$filehandle = fopen($filename, "w");
			fwrite($filehandle, $ser);
			fclose($filehandle);
			$count--;
		}
	}

	public function tearDown(){
		$finder = Finder::create()->in(__DIR__."/mock.spool.path")->name('*');
		foreach ($finder as $next){
			unlink($next);
		}

	}
}