<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Command;

use Azine\EmailBundle\Command\SendNotificationsCommand;
use Azine\EmailBundle\Services\NotifierServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

class SendNotificationsCommandTest extends TestCase
{
    public function testHelpExplainsSymfonyMailerDelivery(): void
    {
        $command = $this->createCommand();

        self::assertStringContainsString('Symfony Mailer transport', $command->getHelp());
        self::assertStringContainsString('Messenger', $command->getHelp());
    }

    public function testSendsNotifications(): void
    {
        $tester = new CommandTester($this->createCommand());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('10 emails have been processed.', $tester->getDisplay());
    }

    public function testReportsFailedRecipients(): void
    {
        $tester = new CommandTester($this->createCommand(true));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('9 emails have been processed.', $tester->getDisplay());
        self::assertStringContainsString('a.failed@address.com', $tester->getDisplay());
    }

    public function testDoesNotRunWhenLockIsUnavailable(): void
    {
        $notifier = $this->createMock(NotifierServiceInterface::class);
        $notifier->expects(self::never())->method('sendNotifications');
        $tester = new CommandTester($this->register(
            new SendNotificationsCommand($notifier, $this->createLockFactory(false)),
        ));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('already running', $tester->getDisplay());
    }

    private function createCommand(bool $fail = false): SendNotificationsCommand
    {
        $notifier = $this->createMock(NotifierServiceInterface::class);
        $notifier
            ->expects(self::once())
            ->method('sendNotifications')
            ->willReturnCallback(static function (array &$failedAddresses) use ($fail): int {
                if ($fail) {
                    $failedAddresses[] = 'a.failed@address.com';

                    return 9;
                }

                return 10;
            });

        return $this->register(new SendNotificationsCommand($notifier, $this->createLockFactory(true)));
    }

    private function createLockFactory(bool $acquired): LockFactory
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects(self::once())->method('acquire')->willReturn($acquired);
        $lock->expects($acquired ? self::once() : self::never())->method('release');

        $factory = $this->getMockBuilder(LockFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createLock'])
            ->getMock();
        $factory->expects(self::once())->method('createLock')->willReturn($lock);

        return $factory;
    }

    private function register(SendNotificationsCommand $command): SendNotificationsCommand
    {
        $application = new Application();
        $application->add($command);

        /** @var SendNotificationsCommand $registered */
        $registered = $application->find('emails:sendNotifications');

        return $registered;
    }
}
