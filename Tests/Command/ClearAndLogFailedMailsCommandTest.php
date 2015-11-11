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
class ClearAndLogFailedMailsCommandTest extends \PHPUnit_Framework_TestCase
{
	public function testHelpInfo()
    {
        $command = $this->getCommand();

        $display = $command->getHelp();
        $this->assertContains("Any email-address that still failed, is logged.", $display);
    }

    public function testSendingFailedMails()
    {
        $command = $this->getCommand();
        $failedRecipients = array('failed@email.com');
        $count = 2;

        $this->createFakeFailedMessageFiles($count);
        $command->setContainer($this->getMockSetup($failedRecipients, false, false, $this->exactly($count)));

        $display = $this->executeCommandAndGetDisplay($command, array(''));
        $this->assertContains("Retrying to send 'subject blabbla' to 'test-recipient@test.com'", $display);
        $this->assertContains("Sent!", $display);
    }

    public function testSendingFailedMailsWithDate()
    {
        $command = $this->getCommand();
        $failedRecipients = array('failed@email.com');
        $count = 4;
        $this->createFakeFailedMessageFiles($count);

        $command->setContainer($this->getMockSetup($failedRecipients, false, false, $this->exactly($count)));

        $display = $this->executeCommandAndGetDisplay($command, array('date' => ' > now -1 minute'));
        $this->assertContains("Retrying to send 'subject blabbla' to 'test-recipient@test.com'", $display);
        $this->assertContains("Sent!", $display);
    }

    public function testSendingFailedMailsNoMailsFound()
    {
        $command = $this->getCommand();
        $failedRecipients = array();
        $command->setContainer($this->getMockSetup($failedRecipients, false, false, $this->never()));

        $display = $this->executeCommandAndGetDisplay($command, array(''));

        $this->assertContains("No failed-message-files found", $display);
    }

    public function testSendingFailedMailsWithoutTransport()
    {
        $command = $this->getCommand();
        $failedRecipients = array('failed@email.com');
        $command->setContainer($this->getMockSetup($failedRecipients, false, true));

        $display = $this->executeCommandAndGetDisplay($command, array(''));

        $this->assertContains("Could not load transport. Is file-spooling configured in your config.yml for this environment?", $display);
    }

    public function testSendingFailedMailsWithoutSpooling()
    {
        $command = $this->getCommand();
        $failedRecipients = array('failed@email.com');
        $command->setContainer($this->getMockSetup($failedRecipients, true));

        $display = $this->executeCommandAndGetDisplay($command, array(''));

        $this->assertContains("Could not find file spool path. Is file-spooling configured in your config.yml for this environment?", $display);
    }

    /**
     * @param string[] $failedRecipients
     * @param bool $noSpoolPath
     * @param bool $noTransport
     * @param null $msgCount
     * @internal param string $message
     * @return ContainerInterface
     */
    private function getMockSetup($failedRecipients, $noSpoolPath = false, $noTransport = false, $msgCount = null)
    {
        if ($msgCount == null) {
            $msgCount = $this->once();
        }

        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();

        if ($noTransport) {
            $containerMock->expects($this->once())->method('get')->will($this->throwException(new ServiceNotFoundException('swiftmailer.transport.real')));

            return $containerMock;
        }

        $transportMock = $this->getMockBuilder("\Swift_SmtpTransport")->getMock();

        if ($noSpoolPath) {
            $containerMock->expects($this->once())->method('get')->will($this->returnValue($transportMock));
            $containerMock->expects($this->once())->method('getParameter')->will($this->throwException(new InvalidArgumentException()));

            return $containerMock;
        }

        $loggerMock = $this->getMockBuilder("Psr\Log\LoggerInterface")->disableOriginalConstructor()->getMock();
        if(sizeof($failedRecipients) > 0){
        	$loggerMock->expects($this->once())->method("warning")->with("<error>Failed to send an email to : ".implode(", ", $failedRecipients)."</error>");
        	$getServiceCallCount = $this->exactly(2);
        } else {
        	$loggerMock->expects($this->never())->method("warning");
        	$getServiceCallCount = $this->once();
        }

        $transportMock->expects($this->once())->method("isStarted")->will($this->returnValue(false));
        $transportMock->expects($this->once())->method("start");
        $this->failedRecipients = $failedRecipients;
        $transportMock->expects($msgCount)->method("send")->will($this->returnCallback(array($this, 'send_failures_callback')));

        $containerMock->expects($getServiceCallCount)->method('get')->will($this->returnValueMap(array(
        																							array('swiftmailer.transport.real', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $transportMock),
        																							array('logger', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $loggerMock),
        																					)));
        $containerMock->expects($this->exactly(2))->method('getParameter')->will($this->returnValueMap(array(
                                                                                                    array('swiftmailer.mailers', array('default_mailer' => 'a dummy value for a mailer')),
                                                                                                    array('swiftmailer.spool.default_mailer.file.path', __DIR__."/mock.spool.path"),
        																					)));

        return $containerMock;
    }

    private $failedRecipients = array();

    public function send_failures_callback(\Swift_Mime_Message $message, &$failedRecipients = null){
    	if(sizeof($this->failedRecipients) > 0){
    		$failedRecipients[] = array_pop($this->failedRecipients);
    	}
    }

    private function createFakeFailedMessageFiles($count = 1)
    {
    	$targetDir = __DIR__."/mock.spool.path/";

		$i = 0;
    	while ($i < $count) {
            $random = md5(date('now')).$count.rand(0, 10000000);
            $filename = $targetDir."$random.sending";
            $msg = new \Swift_Message();
            $msg->setTo("test-recipient@test.com");
            $msg->setBody("random file $random bla bla.");
            $msg->setSubject("subject blabbla");
            $msg->setSender("test@test.com");
            $ser = serialize($msg);
            $filehandle = fopen($filename, "w");
            fwrite($filehandle, $ser);
            fclose($filehandle);
            $i++;
        }

        // make sure the right number of files has been created.
        $fileCount = 0;
        $targetDirHandle = opendir($targetDir);
		while(($file = readdir($targetDirHandle)) !== false){
			$fileCount++;
		}
		$this->assertEquals($count + 3, $fileCount, "Exactly $count + 2 files (*.sending, '.', '..' and '.keepMe') expected in this directory( $targetDir ).");
    }

    /**
     * @return ClearAndLogFailedMailsCommand
     */
    private function getCommand(){
    	$application = new Application();
    	$application->add(new ClearAndLogFailedMailsCommand());

    	return $application->find('emails:clear-and-log-failures');
    }

    /**
     *
     * @param ClearAndLogFailedMailsCommand $command
     * @param array $input
     * @return string
     */
    private function executeCommandAndGetDisplay($command, $input){
    	$tester = new CommandTester($command);
    	$tester->execute($input);
    	$display = $tester->getDisplay();
    	return $display;
    }

    public function tearDown(){
    	parent::tearDown();
        $finder = Finder::create()->in(__DIR__."/mock.spool.path/")->name('*');
        foreach ($finder as $next) {
            unlink($next);
        }
    }
}
