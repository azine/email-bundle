<?php
namespace Azine\EmailBundle\Tests\Command;

use Azine\EmailBundle\Command\SendNotificationsCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;
use Symfony\Component\Process\Process;

/**
 * @author dominik
 */
class SendNotificationsCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testHelpInfo()
    {
        $command = $this->getCommand();
    	$display = $command->getHelp();
        $this->assertContains("Depending on you Swiftmailer-Configuration the email will be send directly or will be written to the spool.", $display);
    }

    public function testSend()
    {
        $command = $this->getCommand();
        $tester = new CommandTester($command);
        $tester->execute(array(''));
        $display = $tester->getDisplay();
        $this->assertContains(AzineNotifierServiceMock::EMAIL_COUNT." emails have been processed.", $display);
    }

    public function testSendFail()
    {
    	$command = $this->getCommand(true);
        $tester = new CommandTester($command);
        $tester->execute(array(''));
        $display = $tester->getDisplay();
        $this->assertContains((AzineNotifierServiceMock::EMAIL_COUNT-1)." emails have been processed.", $display);
        $this->assertContains(AzineNotifierServiceMock::FAILED_ADDRESS, $display);
    }

	/**
	 * @return SendNotificationsCommand
	 */
    private function getCommand($fail = false){
    	$application = new Application();
    	$application->add(new SendNotificationsCommand());
    	$command = $application->find('emails:sendNotifications');
        $command->setContainer($this->getMockSetup($fail));
        return $command;
    }

    private function getMockSetup($fail = false)
    {
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
        $notifierServiceMock = new AzineNotifierServiceMock($fail);
        $containerMock->expects($this->any())->method('get')->with('azine_email_notifier_service')->will($this->returnValue($notifierServiceMock));
        return $containerMock;
    }

    public function testLockingFunctionality()
    {
        if(!class_exists('AppKernel')){
            $this->markTestSkipped("This test does only work if a full application is installed (including AppKernel class");
        }
        $commandName = $this->getCommand()->getName();
        $reflector = new \ReflectionClass(\AppKernel::class);
        $appDirectory = dirname($reflector->getFileName());

        // start commands in a separate processes
        $process1 = new Process("php $appDirectory/console $commandName --env=test");
        $process2 = new Process("php $appDirectory/console $commandName --env=test");
        $process1->start();
        $process2->start();

        // wait until both processes have terminated
        while(!$process1->isTerminated() || !$process2->isTerminated()){
            usleep(10);
        }

        $this->assertContains('The command is already running in another process.', $process2->getOutput().$process1->getOutput());
    }
}
