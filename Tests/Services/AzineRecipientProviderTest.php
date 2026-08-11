<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\Entity\RecipientInterface;
use Azine\EmailBundle\Services\AzineRecipientProvider;
use Azine\EmailBundle\Tests\AzineQueryMock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

class AzineRecipientProviderTest extends TestCase
{
    public function testGetRecipient(): void
    {
        $recipient = $this->createMock(RecipientInterface::class);
        $recipient->method('getId')->willReturn(11);

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())->method('find')->with(11)->willReturn($recipient);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getRepository')->with('a-user-class')->willReturn($repository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);

        $provider = new AzineRecipientProvider($registry, 'a-user-class', 'newsletterField');

        self::assertSame($recipient, $provider->getRecipient(11));
    }

    public function testMissingRecipientThrowsUsefulException(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('find')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No recipient');

        (new AzineRecipientProvider($registry, 'a-user-class', 'newsletterField'))->getRecipient(11);
    }

    public function testGetNewsletterRecipientIds(): void
    {
        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'where', 'andWhere', 'getQuery'])
            ->getMock();
        $queryBuilder->expects(self::once())->method('select')->with('recipient.id')->willReturnSelf();
        $queryBuilder->expects(self::once())->method('from')->with('a-user-class', 'recipient')->willReturnSelf();
        $queryBuilder
            ->expects(self::once())
            ->method('where')
            ->with('recipient.newsletterField = true')
            ->willReturnSelf();
        $queryBuilder
            ->expects(self::once())
            ->method('andWhere')
            ->with('recipient.enabled = true')
            ->willReturnSelf();
        $queryBuilder
            ->expects(self::once())
            ->method('getQuery')
            ->willReturn(new AzineQueryMock([
                ['id' => 11],
                ['id' => 12],
                ['id' => 13],
                ['id' => 14],
            ]));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);

        $provider = new AzineRecipientProvider($registry, 'a-user-class', 'newsletterField');

        self::assertSame([11, 12, 13, 14], $provider->getNewsletterRecipientIDs());
    }
}
