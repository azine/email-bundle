<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Entity\Notification;
use Azine\EmailBundle\Entity\RecipientInterface;
use Azine\EmailBundle\Entity\Repositories\NotificationRepository;
use Azine\EmailBundle\Services\AzineNotifierService;
use Azine\EmailBundle\Services\RecipientProviderInterface;
use Azine\EmailBundle\Services\TemplateProviderInterface;
use Azine\EmailBundle\Services\TemplateTwigMailerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AzineNotifierServiceTest extends TestCase
{
    public function testAddNotificationPersistsAllValues(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(Notification::class));
        $entityManager->expects(self::once())->method('flush');

        $notifier = $this->createNotifier(entityManager: $entityManager);
        $notification = $notifier->addNotification(
            12,
            'A title',
            'Some content',
            '@App/Email/item',
            ['foo' => 'bar'],
            1,
            true,
        );

        self::assertSame('12', (string) $notification->getRecipientId());
        self::assertSame('A title', $notification->getTitle());
        self::assertSame('Some content', $notification->getContent());
        self::assertSame('@App/Email/item', $notification->getTemplate());
        self::assertSame(['foo' => 'bar'], $notification->getVariables());
        self::assertTrue($notification->getSendImmediately());
    }

    public function testAddNotificationMessageUsesConfiguredContentTemplate(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Notification $notification): bool {
                return '@App/Email/message' === $notification->getTemplate()
                    && ['goToUrl' => 'https://azine.me/messages', 'base' => 'value'] === $notification->getVariables();
            }));
        $entityManager->expects(self::once())->method('flush');

        $templateProvider = $this->createMock(TemplateProviderInterface::class);
        $templateProvider
            ->expects(self::once())
            ->method('addTemplateVariablesFor')
            ->with('@App/Email/message', ['goToUrl' => 'https://azine.me/messages'])
            ->willReturn(['goToUrl' => 'https://azine.me/messages', 'base' => 'value']);

        $this->createNotifier(
            entityManager: $entityManager,
            templateProvider: $templateProvider,
        )->addNotificationMessage(
            12,
            'Message title',
            'Message body',
            'https://azine.me/messages',
        );
    }

    public function testNewsletterSendsPerRecipientAndCollectsFailures(): void
    {
        $first = $this->createRecipient(11, 'first@example.com');
        $second = $this->createRecipient(12, 'failed@example.com');

        $recipientProvider = $this->createMock(RecipientProviderInterface::class);
        $recipientProvider->method('getNewsletterRecipientIDs')->willReturn([11, 12]);
        $recipientProvider
            ->method('getRecipient')
            ->willReturnMap([[11, $first], [12, $second]]);

        $mailer = $this->createMock(TemplateTwigMailerInterface::class);
        $mailer
            ->expects(self::exactly(2))
            ->method('sendSingleEmail')
            ->willReturnCallback(static fn (string $email): bool => 'failed@example.com' !== $email);

        $failedAddresses = [];
        $sent = $this->createNotifier(
            mailer: $mailer,
            recipientProvider: $recipientProvider,
            withNewsletterContent: true,
        )->sendNewsletter($failedAddresses);

        self::assertSame(1, $sent);
        self::assertSame(['failed@example.com'], $failedAddresses);
    }

    public function testNewsletterWithoutContentIsReportedAsFailedAndNotSent(): void
    {
        $recipient = $this->createRecipient(11, 'recipient@example.com');
        $recipientProvider = $this->createMock(RecipientProviderInterface::class);
        $recipientProvider->method('getNewsletterRecipientIDs')->willReturn([11]);
        $recipientProvider->method('getRecipient')->willReturn($recipient);

        $mailer = $this->createMock(TemplateTwigMailerInterface::class);
        $mailer->expects(self::never())->method('sendSingleEmail');

        $failedAddresses = [];
        $sent = $this->createNotifier(
            mailer: $mailer,
            recipientProvider: $recipientProvider,
        )->sendNewsletter($failedAddresses);

        self::assertSame(0, $sent);
        self::assertSame(['recipient@example.com'], $failedAddresses);
    }

    public function testNotificationDeliveryMarksItemsAsSent(): void
    {
        $recipient = $this->createRecipient(
            11,
            'recipient@example.com',
            RecipientInterface::NOTIFICATION_MODE_IMMEDIATELY,
        );
        $notification = (new Notification())
            ->setTitle('A title')
            ->setContent('A body')
            ->setTemplate('@App/Email/item')
            ->setVariables(['foo' => 'bar']);

        $repository = $this->createNotificationRepository();
        $repository->method('getNotificationRecipientIds')->willReturn([11]);
        $repository->method('getLastNotificationDate')->with(11)->willReturn(new \DateTime('@0'));
        $repository->method('getNotificationsToSend')->with(11)->willReturn([$notification]);

        $recipientProvider = $this->createMock(RecipientProviderInterface::class);
        $recipientProvider->method('getRecipient')->with(11)->willReturn($recipient);

        $mailer = $this->createMock(TemplateTwigMailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('sendSingleEmail')
            ->with(
                'recipient@example.com',
                'Recipient 11',
                'A title',
                self::callback(static fn (array $params): bool => isset($params[AzineNotifierService::CONTENT_ITEMS])),
                '@App/Email/notifications.txt.twig',
                'en',
            )
            ->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($notification);
        $entityManager->expects(self::once())->method('flush');

        $failedAddresses = [];
        $sent = $this->createNotifier(
            mailer: $mailer,
            recipientProvider: $recipientProvider,
            notificationRepository: $repository,
            entityManager: $entityManager,
        )->sendNotifications($failedAddresses);

        self::assertSame(1, $sent);
        self::assertSame([], $failedAddresses);
        self::assertInstanceOf(\DateTime::class, $notification->getSent());
    }

    public function testNeverModeMarksPendingNotificationsAsHandledWithoutSending(): void
    {
        $recipient = $this->createRecipient(
            11,
            'recipient@example.com',
            RecipientInterface::NOTIFICATION_MODE_NEVER,
        );
        $repository = $this->createNotificationRepository();
        $repository->method('getNotificationRecipientIds')->willReturn([11]);
        $repository->method('getLastNotificationDate')->willReturn(new \DateTime('@0'));
        $repository
            ->expects(self::once())
            ->method('markAllNotificationsAsSentFarInThePast')
            ->with(11);

        $recipientProvider = $this->createMock(RecipientProviderInterface::class);
        $recipientProvider->method('getRecipient')->willReturn($recipient);

        $mailer = $this->createMock(TemplateTwigMailerInterface::class);
        $mailer->expects(self::never())->method('sendSingleEmail');

        $failedAddresses = [];
        $sent = $this->createNotifier(
            mailer: $mailer,
            recipientProvider: $recipientProvider,
            notificationRepository: $repository,
        )->sendNotifications($failedAddresses);

        self::assertSame(1, $sent);
        self::assertSame([], $failedAddresses);
    }

    public function testPluralNotificationSubjectUsesModernTranslatorApi(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects(self::once())
            ->method('trans')
            ->with('_az.email.notifications.subject.%count%', ['%count%' => 2])
            ->willReturn('2 notifications');

        $subject = $this->createNotifier(translator: $translator)
            ->getRecipientSpecificNotificationsSubject(
                [['item' => []], ['item' => []]],
                $this->createRecipient(11, 'recipient@example.com'),
            );

        self::assertSame('2 notifications', $subject);
    }

    public function testNewsletterScheduleRetainsConfiguredIntervalAndTime(): void
    {
        $notifier = $this->createNotifier();

        self::assertSame('09:00', $notifier->newsletterSendTime());
        self::assertSame(7, $notifier->newsletterInterval());
        self::assertSame(9, (int) $notifier->lastNewsletterDate()->format('H'));
        self::assertSame(9, (int) $notifier->nextNewsletterDate()->format('H'));
    }

    private function createNotifier(
        ?TemplateTwigMailerInterface $mailer = null,
        ?RecipientProviderInterface $recipientProvider = null,
        ?TemplateProviderInterface $templateProvider = null,
        ?NotificationRepository $notificationRepository = null,
        ?EntityManagerInterface $entityManager = null,
        ?TranslatorInterface $translator = null,
        bool $withNewsletterContent = false,
    ): TestNotifierService {
        $mailer ??= $this->createMock(TemplateTwigMailerInterface::class);
        $recipientProvider ??= $this->createMock(RecipientProviderInterface::class);
        $templateProvider ??= $this->createMock(TemplateProviderInterface::class);
        $notificationRepository ??= $this->createNotificationRepository();
        $entityManager ??= $this->createMock(EntityManagerInterface::class);
        if (null === $translator) {
            $translator = $this->createMock(TranslatorInterface::class);
            $translator->method('trans')->willReturnArgument(0);
        }

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);
        $registry
            ->method('getRepository')
            ->with(Notification::class)
            ->willReturn($notificationRepository);

        return new TestNotifierService(
            $mailer,
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $registry,
            $templateProvider,
            $recipientProvider,
            $translator,
            [
                AzineEmailExtension::NEWSLETTER.'_'.AzineEmailExtension::NEWSLETTER_INTERVAL => 7,
                AzineEmailExtension::NEWSLETTER.'_'.AzineEmailExtension::NEWSLETTER_SEND_TIME => '09:00',
                AzineEmailExtension::TEMPLATES.'_'.AzineEmailExtension::NEWSLETTER_TEMPLATE => '@App/Email/newsletter',
                AzineEmailExtension::TEMPLATES.'_'.AzineEmailExtension::NOTIFICATIONS_TEMPLATE => '@App/Email/notifications',
                AzineEmailExtension::TEMPLATES.'_'.AzineEmailExtension::CONTENT_ITEM_TEMPLATE => '@App/Email/message',
            ],
            $withNewsletterContent,
        );
    }

    private function createNotificationRepository(): NotificationRepository
    {
        return $this->getMockBuilder(NotificationRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getNotificationRecipientIds',
                'getLastNotificationDate',
                'getNotificationsToSend',
                'getNotificationsToSendImmediately',
                'markAllNotificationsAsSentFarInThePast',
            ])
            ->getMock();
    }

    private function createRecipient(
        int $id,
        string $email,
        int $notificationMode = RecipientInterface::NOTIFICATION_MODE_IMMEDIATELY,
    ): RecipientInterface {
        $recipient = $this->createMock(RecipientInterface::class);
        $recipient->method('getId')->willReturn($id);
        $recipient->method('getEmail')->willReturn($email);
        $recipient->method('getDisplayName')->willReturn('Recipient '.$id);
        $recipient->method('getPreferredLocale')->willReturn('en');
        $recipient->method('getNotificationMode')->willReturn($notificationMode);
        $recipient->method('getNewsletter')->willReturn(true);

        return $recipient;
    }
}

final class TestNotifierService extends AzineNotifierService
{
    public function __construct(
        TemplateTwigMailerInterface $mailer,
        Environment $twig,
        UrlGeneratorInterface $router,
        ManagerRegistry $managerRegistry,
        TemplateProviderInterface $templateProvider,
        RecipientProviderInterface $recipientProvider,
        TranslatorInterface $translatorService,
        array $parameters,
        private readonly bool $withNewsletterContent,
    ) {
        parent::__construct(
            $mailer,
            $twig,
            $router,
            $managerRegistry,
            $templateProvider,
            $recipientProvider,
            $translatorService,
            $parameters,
        );
    }

    protected function getNonRecipientSpecificNewsletterContentItems()
    {
        return $this->withNewsletterContent
            ? [['@App/Email/item' => ['title' => 'General item']]]
            : [];
    }

    public function newsletterSendTime(): string
    {
        return (string) $this->getNewsletterSendTime();
    }

    public function newsletterInterval(): int
    {
        return (int) $this->getNewsletterInterval();
    }

    public function lastNewsletterDate(): \DateTime
    {
        return $this->getDateTimeOfLastNewsletter();
    }

    public function nextNewsletterDate(): \DateTime
    {
        return $this->getDateTimeOfNextNewsletter();
    }
}
