<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Entity\Notification;
use Azine\EmailBundle\Entity\RecipientInterface;
use Azine\EmailBundle\Entity\Repositories\NotificationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Compiles and sends notification and newsletter emails.
 *
 * The protected/public extension hooks intentionally retain their historical
 * signatures so application-specific notifier subclasses remain source-compatible.
 */
class AzineNotifierService implements NotifierServiceInterface
{
    public const CONTENT_ITEMS = 'contentItems';

    protected TemplateTwigMailerInterface $mailer;
    protected Environment $twig;
    protected UrlGeneratorInterface $router;
    protected TemplateProviderInterface $templateProvider;
    protected RecipientProviderInterface $recipientProvider;
    protected ManagerRegistry $managerRegistry;
    protected array $configParameter;
    protected TranslatorInterface $translatorService;

    public function __construct(
        TemplateTwigMailerInterface $mailer,
        Environment $twig,
        UrlGeneratorInterface $router,
        ManagerRegistry $managerRegistry,
        TemplateProviderInterface $templateProvider,
        RecipientProviderInterface $recipientProvider,
        TranslatorInterface $translatorService,
        array $parameters,
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->router = $router;
        $this->managerRegistry = $managerRegistry;
        $this->templateProvider = $templateProvider;
        $this->recipientProvider = $recipientProvider;
        $this->translatorService = $translatorService;
        $this->configParameter = $parameters;
    }

    protected function getVarsForNotificationsEmail()
    {
        return [];
    }

    protected function getRecipientVarsForNotificationsEmail(RecipientInterface $recipient)
    {
        return [
            'recipient' => $recipient,
            'mode' => $recipient->getNotificationMode(),
        ];
    }

    public function getRecipientSpecificNotificationsSubject($contentItems, RecipientInterface $recipient)
    {
        $count = count($contentItems);
        if (1 === $count) {
            $boxedItem = current($contentItems);
            $onlyItem = is_array($boxedItem) ? current($boxedItem) : null;
            if (is_array($onlyItem) && isset($onlyItem['notification'])) {
                return $onlyItem['notification']->getTitle();
            }
        }

        return $this->translatorService->trans(
            '_az.email.notifications.subject.%count%',
            ['%count%' => $count],
        );
    }

    protected function getGeneralVarsForNewsletter()
    {
        return ['recipientCount' => count($this->recipientProvider->getNewsletterRecipientIDs())];
    }

    protected function getNonRecipientSpecificNewsletterContentItems()
    {
        return [];
    }

    public function getRecipientSpecificNewsletterParams(RecipientInterface $recipient)
    {
        return ['recipient' => $recipient];
    }

    protected function getRecipientSpecificNewsletterContentItems(RecipientInterface $recipient)
    {
        return [];
    }

    public function getRecipientSpecificNewsletterSubject(
        array $generalContentItems,
        array $recipientContentItems,
        array $params,
        RecipientInterface $recipient,
        $locale,
    ) {
        return $params['subject'];
    }

    public function orderContentItems(array $contentItems)
    {
        return $contentItems;
    }

    protected function getHourInterval()
    {
        return 60 * 60 - 3 * 60;
    }

    protected function getDayInterval()
    {
        return 60 * 60 * 24 - 3 * 60;
    }

    public function sendNotifications(array &$failedAddresses)
    {
        $recipientIds = $this->getNotificationRecipientIds();
        $params = $this->getVarsForNotificationsEmail();
        $notificationsTemplate = $this->configParameter[
            AzineEmailExtension::TEMPLATES.'_'.AzineEmailExtension::NOTIFICATIONS_TEMPLATE
        ];

        $sentCount = 0;
        foreach ($recipientIds as $recipientId) {
            $failedAddress = $this->sendNotificationsFor(
                $recipientId,
                $notificationsTemplate,
                $params,
            );

            if (is_string($failedAddress) && '' !== $failedAddress) {
                $failedAddresses[] = $failedAddress;
            } else {
                ++$sentCount;
            }
        }

        return $sentCount;
    }

    public function sendNotificationsFor($recipientId, $wrapperTemplateName, $params)
    {
        $recipient = $this->recipientProvider->getRecipient($recipientId);
        $notifications = $this->getNotificationsFor($recipient);
        if ([] === $notifications) {
            return null;
        }

        $params = array_merge(
            $this->getRecipientVarsForNotificationsEmail($recipient),
            $params,
        );

        $contentItems = [];
        foreach ($notifications as $notification) {
            $itemVariables = array_merge($params, $notification->getVariables());
            $itemVariables['notification'] = $notification;
            $itemVariables['recipient'] = $recipient;
            $contentItems[] = [$notification->getTemplate() => $itemVariables];
        }

        $params[self::CONTENT_ITEMS] = $contentItems;
        $params['recipient'] = $recipient;
        $params['_locale'] = $recipient->getPreferredLocale();
        $subject = $this->getRecipientSpecificNotificationsSubject($contentItems, $recipient);

        $sent = $this->mailer->sendSingleEmail(
            $recipient->getEmail(),
            $recipient->getDisplayName(),
            $subject,
            $params,
            $wrapperTemplateName.'.txt.twig',
            $recipient->getPreferredLocale(),
        );

        if (!$sent) {
            return $recipient->getEmail();
        }

        $this->setNotificationsAsSent($notifications);

        return null;
    }

    public function sendNewsletter(array &$failedAddresses)
    {
        $params = [
            'subject' => $this->translatorService->trans('_az.email.newsletter.subject'),
            self::CONTENT_ITEMS => $this->getNonRecipientSpecificNewsletterContentItems(),
        ];
        $recipientIds = $this->recipientProvider->getNewsletterRecipientIDs();
        $newsletterTemplate = $this->configParameter[
            AzineEmailExtension::TEMPLATES.'_'.AzineEmailExtension::NEWSLETTER_TEMPLATE
        ];

        foreach ($recipientIds as $recipientId) {
            $failedAddress = $this->sendNewsletterFor(
                $recipientId,
                $params,
                $newsletterTemplate,
            );
            if (is_string($failedAddress) && '' !== $failedAddress) {
                $failedAddresses[] = $failedAddress;
            }
        }

        return count($recipientIds) - count($failedAddresses);
    }

    public function sendNewsletterFor($recipientId, array $params, $wrapperTemplate)
    {
        $recipient = $this->recipientProvider->getRecipient($recipientId);
        $recipientParams = array_merge(
            $params,
            $this->getGeneralVarsForNewsletter(),
            $this->getRecipientSpecificNewsletterParams($recipient),
        );
        $recipientContentItems = $this->getRecipientSpecificNewsletterContentItems($recipient);
        $recipientParams[self::CONTENT_ITEMS] = $this->orderContentItems(array_merge(
            $recipientContentItems,
            $params[self::CONTENT_ITEMS],
        ));
        $recipientParams['_locale'] = $recipient->getPreferredLocale();

        if ([] === $recipientParams[self::CONTENT_ITEMS]) {
            return $recipient->getEmail();
        }

        $subject = $this->getRecipientSpecificNewsletterSubject(
            $params[self::CONTENT_ITEMS],
            $recipientContentItems,
            $params,
            $recipient,
            $recipient->getPreferredLocale(),
        );

        $sent = $this->mailer->sendSingleEmail(
            $recipient->getEmail(),
            $recipient->getDisplayName(),
            $subject,
            $recipientParams,
            $wrapperTemplate.'.txt.twig',
            $recipient->getPreferredLocale(),
        );

        return $sent ? null : $recipient->getEmail();
    }

    protected function getNotificationsFor(RecipientInterface $recipient)
    {
        $notificationMode = $recipient->getNotificationMode();
        $lastNotificationDate = $this->getNotificationRepository()->getLastNotificationDate(
            $recipient->getId(),
        );
        $timeDelta = time() - $lastNotificationDate->getTimestamp();

        if (RecipientInterface::NOTIFICATION_MODE_NEVER === $notificationMode) {
            $this->markAllNotificationsAsSentFarInThePast($recipient);

            return [];
        }

        $sendNotifications = match ($notificationMode) {
            RecipientInterface::NOTIFICATION_MODE_IMMEDIATELY => true,
            RecipientInterface::NOTIFICATION_MODE_HOURLY => $timeDelta > $this->getHourInterval(),
            RecipientInterface::NOTIFICATION_MODE_DAYLY => $timeDelta > $this->getDayInterval(),
            default => false,
        };

        return $sendNotifications
            ? $this->getNotificationRepository()->getNotificationsToSend($recipient->getId())
            : $this->getNotificationRepository()->getNotificationsToSendImmediately($recipient->getId());
    }

    protected function getNotificationRecipientIds()
    {
        return $this->getNotificationRepository()->getNotificationRecipientIds();
    }

    protected function setNotificationsAsSent(array $notifications)
    {
        $entityManager = $this->managerRegistry->getManager();
        foreach ($notifications as $notification) {
            $notification->setSent(new \DateTime());
            $entityManager->persist($notification);
        }
        $entityManager->flush();
    }

    protected function markAllNotificationsAsSentFarInThePast(RecipientInterface $recipient)
    {
        $this->getNotificationRepository()->markAllNotificationsAsSentFarInThePast(
            $recipient->getId(),
        );
    }

    protected function getNewsletterInterval()
    {
        return $this->configParameter[
            AzineEmailExtension::NEWSLETTER.'_'.AzineEmailExtension::NEWSLETTER_INTERVAL
        ];
    }

    protected function getNewsletterSendTime()
    {
        return $this->configParameter[
            AzineEmailExtension::NEWSLETTER.'_'.AzineEmailExtension::NEWSLETTER_SEND_TIME
        ];
    }

    protected function getDateTimeOfLastNewsletter()
    {
        return new \DateTime($this->getNewsletterInterval().' days ago '.$this->getNewsletterSendTime());
    }

    protected function getDateTimeOfNextNewsletter()
    {
        return new \DateTime('+'.$this->getNewsletterInterval().' days '.$this->getNewsletterSendTime());
    }

    public function addNotification(
        $recipientId,
        $title,
        $content,
        $template,
        $templateVars,
        $importance,
        $sendImmediately,
    ) {
        $notification = new Notification();
        $notification->setRecipientId($recipientId);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setTemplate($template);
        $notification->setImportance($importance);
        $notification->setSendImmediately($sendImmediately);
        $notification->setVariables($templateVars);

        $entityManager = $this->managerRegistry->getManager();
        $entityManager->persist($notification);
        $entityManager->flush();

        return $notification;
    }

    public function addNotificationMessage($recipientId, $title, $content, $goToUrl = null)
    {
        $contentItemTemplate = $this->configParameter[
            AzineEmailExtension::TEMPLATES.'_'.AzineEmailExtension::CONTENT_ITEM_TEMPLATE
        ];
        $templateVariables = [];
        if (is_string($goToUrl) && '' !== $goToUrl) {
            $templateVariables['goToUrl'] = $goToUrl;
        }

        return $this->addNotification(
            $recipientId,
            $title,
            $content,
            $contentItemTemplate,
            $this->templateProvider->addTemplateVariablesFor(
                $contentItemTemplate,
                $templateVariables,
            ),
            Notification::IMPORTANCE_NORMAL,
            false,
        );
    }

    protected function getNotificationRepository()
    {
        $repository = $this->managerRegistry->getRepository(Notification::class);
        if (!$repository instanceof NotificationRepository) {
            throw new \LogicException(sprintf(
                'Expected repository "%s", got "%s".',
                NotificationRepository::class,
                get_debug_type($repository),
            ));
        }

        return $repository;
    }
}
