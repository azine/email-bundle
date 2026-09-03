<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests;

use Azine\EmailBundle\Entity\Notification;
use Azine\EmailBundle\Entity\Repositories\NotificationRepository;
use Azine\EmailBundle\Entity\Repositories\SentEmailRepository;
use Azine\EmailBundle\Entity\SentEmail;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use PHPUnit\Framework\TestCase;

final class DoctrineMappingTest extends TestCase
{
    private XmlDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new XmlDriver(__DIR__.'/../Resources/config/doctrine', '.orm.xml');
    }

    public function testNotificationMappingLoadsWithDoctrineOrm3(): void
    {
        $metadata = new ClassMetadata(Notification::class);

        $this->driver->loadMetadataForClass(Notification::class, $metadata);

        self::assertSame('notification', $metadata->getTableName());
        self::assertSame(NotificationRepository::class, $metadata->customRepositoryClassName);
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertSame('json', $metadata->getTypeOfField('variables'));
        self::assertTrue($metadata->hasLifecycleCallbacks('prePersist'));
        self::assertSame(['setCreatedValue'], $metadata->getLifecycleCallbacks('prePersist'));
    }

    public function testSentEmailMappingLoadsWithDoctrineOrm3(): void
    {
        $metadata = new ClassMetadata(SentEmail::class);

        $this->driver->loadMetadataForClass(SentEmail::class, $metadata);

        self::assertSame('sent_email', $metadata->getTableName());
        self::assertSame(SentEmailRepository::class, $metadata->customRepositoryClassName);
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertSame('array', $metadata->getTypeOfField('recipients'));
        self::assertSame('array', $metadata->getTypeOfField('variables'));
    }

    public function testDriverDiscoversBothBundleEntities(): void
    {
        $classes = $this->driver->getAllClassNames();
        sort($classes);

        self::assertSame([Notification::class, SentEmail::class], $classes);
    }
}
