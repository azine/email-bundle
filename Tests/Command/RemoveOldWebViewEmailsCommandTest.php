<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Command;

use Azine\EmailBundle\Command\RemoveOldWebViewEmailsCommand;
use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Tests\AzineQueryMock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RemoveOldWebViewEmailsCommandTest extends TestCase
{
    public function testHelpInfo(): void
    {
        $command = $this->createCommand(90, 0);

        self::assertStringContainsString('deletes SentEmail entities', $command->getHelp());
        self::assertStringContainsString('Remove stored email web views', $command->getDescription());
    }

    public function testDeletesUsingConfiguredRetention(): void
    {
        $command = $this->createCommand(66, 9);
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Using the configured retention period: 66 days.', $tester->getDisplay());
        self::assertStringContainsString('9 SentEmails older than', $tester->getDisplay());
    }

    public function testCommandArgumentOverridesConfiguredRetention(): void
    {
        $command = $this->createCommand(66, 900);
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute(['keep' => 121]));
        self::assertStringContainsString('900 SentEmails older than', $tester->getDisplay());
        self::assertStringNotContainsString('configured retention period', $tester->getDisplay());
    }

    public function testRejectsInvalidRetention(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManager');
        $tester = new CommandTester($this->register(new RemoveOldWebViewEmailsCommand($registry, 0)));

        self::assertSame(Command::INVALID, $tester->execute([]));
        self::assertStringContainsString('must be at least one day', $tester->getDisplay());
    }

    private function createCommand(int $retentionDays, int $deletedWebMails): RemoveOldWebViewEmailsCommand
    {
        $query = new AzineQueryMock($deletedWebMails);
        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete', 'where', 'setParameter', 'getQuery'])
            ->getMock();
        $queryBuilder
            ->expects(self::once())
            ->method('delete')
            ->with(SentEmail::class, 's')
            ->willReturnSelf();
        $queryBuilder->expects(self::once())->method('where')->with('s.sent < :sent')->willReturnSelf();
        $queryBuilder
            ->expects(self::once())
            ->method('setParameter')
            ->with('sent', self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturnSelf();
        $queryBuilder->expects(self::once())->method('getQuery')->willReturn($query);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManager')->willReturn($entityManager);

        return $this->register(new RemoveOldWebViewEmailsCommand($registry, $retentionDays));
    }

    private function register(RemoveOldWebViewEmailsCommand $command): RemoveOldWebViewEmailsCommand
    {
        $application = new Application();
        $application->add($command);

        /** @var RemoveOldWebViewEmailsCommand $registered */
        $registered = $application->find('emails:remove-old-web-view-emails');

        return $registered;
    }
}
