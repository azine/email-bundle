<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Entity\SentEmail;
use Doctrine\Persistence\ManagerRegistry;
use FOS\UserBundle\Mailer\MailerInterface as FosUserMailerInterface;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\TemplateWrapper;

/**
 * Renders multipart Twig emails and sends them through Symfony Mailer.
 *
 * The service deliberately keeps the historic public methods used by Azine.Me
 * while replacing the abandoned Swiftmailer implementation underneath them.
 */
class AzineTwigMailer implements TemplateTwigMailerInterface, FosUserMailerInterface
{
    /** @var array<string, TemplateWrapper> */
    private array $templateCache = [];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RouterInterface $router,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly TemplateProviderInterface $templateProvider,
        private readonly ManagerRegistry $managerRegistry,
        private readonly ?EmailOpenTrackingCodeBuilderInterface $emailOpenTrackingCodeBuilder,
        private readonly AzineEmailTwigExtension $emailTwigExtension,
        private readonly array $parameters,
        private readonly ?MailerInterface $immediateMailer = null,
    ) {
    }

    public function sendEmail(
        array &$failedRecipients,
        string $subject,
        ?string $from,
        ?string $fromName,
        string|array $to,
        ?string $toName,
        string|array|null $cc,
        ?string $ccName,
        string|array|null $bcc,
        ?string $bccName,
        string|array|null $replyTo,
        ?string $replyToName,
        array $params,
        string $template,
        array $attachments = [],
        ?string $emailLocale = null,
        ?Email &$message = null,
    ): int {
        $message ??= new Email();
        $failedRecipients = [];

        [$defaultFromEmail, $defaultFromName] = $this->getNoReplyAddress();
        $from ??= $defaultFromEmail;
        $fromName ??= $defaultFromName;

        $params['sendMailAccountName'] ??= $defaultFromName;
        $params['sendMailAccountAddress'] ??= $defaultFromEmail;

        $templateBaseId = $this->getTemplateBaseId($template);
        $saveWebView = $this->templateProvider->saveWebViewFor($templateBaseId);
        $webViewParams = $saveWebView ? $params : [];

        if ($saveWebView) {
            $params[$this->templateProvider->getWebViewTokenId()] = SentEmail::getNewToken();
        }

        $params = $this->templateProvider->addTemplateVariablesFor($templateBaseId, $params);
        $embeddedItems = $this->prepareEmbeddedItems($params);

        $localeAwareTranslator = $this->translator instanceof LocaleAwareInterface ? $this->translator : null;
        $previousLocale = null !== $localeAwareTranslator
            ? (string) $localeAwareTranslator->getLocale()
            : ($emailLocale ?? 'en');
        $emailLocale ??= $previousLocale;

        $routerContext = $this->router->getContext();
        $previousRouteLocale = $routerContext->getParameter('_locale');

        $localeAwareTranslator?->setLocale($emailLocale);
        $routerContext->setParameter('_locale', $emailLocale);

        try {
            $params = $this->templateProvider->addTemplateSnippetsWithImagesFor(
                $templateBaseId,
                $params,
                $emailLocale,
            );
            $params['emailLocale'] = $emailLocale;

            $twigTemplate = $this->loadTemplate($template);
            $textBody = $twigTemplate->renderBlock('body_text', $params);
            $htmlBody = $twigTemplate->renderBlock('body_html', $params);

            $campaignParams = $this->templateProvider->getCampaignParamsFor($templateBaseId, $params);
            if ([] !== $campaignParams) {
                $htmlBody = $this->emailTwigExtension->addCampaignParamsToAllUrls($htmlBody, $campaignParams);
            }

            $messageId = $this->createMessageId();
            $message->getHeaders()->addIdHeader('Message-ID', $messageId);

            if (null !== $this->emailOpenTrackingCodeBuilder) {
                $trackingCode = $this->emailOpenTrackingCodeBuilder->getTrackingImgCode(
                    $templateBaseId,
                    $campaignParams,
                    $params,
                    $messageId,
                    $to,
                    $cc,
                    $bcc,
                );
                if (is_string($trackingCode) && '' !== $trackingCode) {
                    $htmlBody = $this->appendBeforeBodyClose($htmlBody, $trackingCode);
                }
            }

            $message
                ->subject($subject)
                ->from(new Address($from, $fromName ?? ''))
                ->to(...$this->normalizeAddresses($to, $toName))
                ->text($textBody)
                ->html($htmlBody);

            $ccAddresses = $this->normalizeAddresses($cc, $ccName);
            if ([] !== $ccAddresses) {
                $message->cc(...$ccAddresses);
            }

            $bccAddresses = $this->normalizeAddresses($bcc, $bccName);
            if ([] !== $bccAddresses) {
                $message->bcc(...$bccAddresses);
            }

            $replyToAddresses = $this->normalizeAddresses($replyTo ?: $from, $replyToName ?: $fromName);
            if ([] !== $replyToAddresses) {
                $message->replyTo(...$replyToAddresses);
            }

            $this->attachReferencedEmbeddedItems($message, $embeddedItems, $htmlBody);
            $this->attachFiles($message, $attachments);
            $this->templateProvider->addCustomHeaders($templateBaseId, $message, $params);

            try {
                $this->getMailer($params)->send($message);
            } catch (TransportExceptionInterface) {
                $failedRecipients = array_map(
                    static fn (Address $address): string => $address->getAddress(),
                    $message->getTo(),
                );

                return 0;
            }

            if ($saveWebView) {
                $this->storeWebView(
                    $templateBaseId,
                    $webViewParams,
                    $params,
                    $emailLocale,
                    $message,
                    $failedRecipients,
                );
            }

            return 1;
        } finally {
            $localeAwareTranslator?->setLocale($previousLocale);
            $routerContext->setParameter('_locale', $previousRouteLocale);
        }
    }

    public function sendSingleEmail(
        string|array $to,
        ?string $toName,
        string $subject,
        array $params,
        string $template,
        ?string $emailLocale,
        ?string $from = null,
        ?string $fromName = null,
        ?Email &$message = null,
    ): bool {
        $failedRecipients = [];
        $sent = $this->sendEmail(
            $failedRecipients,
            $subject,
            $from,
            $fromName,
            $to,
            $toName,
            null,
            null,
            null,
            null,
            null,
            null,
            $params,
            $template,
            [],
            $emailLocale,
            $message,
        );

        return 1 === $sent && [] === $failedRecipients;
    }

    public function sendConfirmationEmailMessage(UserInterface $user): void
    {
        $template = (string) $this->parameters['template']['confirmation'];
        $url = $this->router->generate(
            'fos_user_registration_confirm',
            ['token' => $user->getConfirmationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->sendAccountMessage(
            $template,
            ['user' => $user, 'confirmationUrl' => $url],
            $this->parameters['from_email']['confirmation'],
            (string) $user->getEmail(),
        );
    }

    public function sendResettingEmailMessage(UserInterface $user): void
    {
        $template = (string) $this->parameters['template']['resetting'];
        $url = $this->router->generate(
            'fos_user_resetting_reset',
            ['token' => $user->getConfirmationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->sendAccountMessage(
            $template,
            ['user' => $user, 'confirmationUrl' => $url],
            $this->parameters['from_email']['resetting'],
            (string) $user->getEmail(),
        );
    }

    /**
     * Backwards-compatible entry point used by the email-update confirmation bundle.
     */
    public function sendEmailUpdateConfirmationMessage(UserInterface $user, string $confirmationUrl): void
    {
        $template = (string) ($this->parameters['template']['email_updating'] ?? '');
        if ('' === $template) {
            throw new \LogicException('No email update confirmation template is configured.');
        }

        $from = $this->parameters['from_email']['email_updating']
            ?? $this->parameters['from_email']['confirmation'];

        $this->sendAccountMessage(
            $template,
            ['user' => $user, 'confirmationUrl' => $confirmationUrl],
            $from,
            (string) $user->getEmail(),
        );
    }

    /**
     * @param array{address?: string, sender_name?: string} $from
     */
    private function sendAccountMessage(string $template, array $parameters, array $from, string $to): void
    {
        $twigTemplate = $this->loadTemplate($template);
        $subject = trim($twigTemplate->renderBlock('subject', $parameters));
        $message = null;

        $this->sendSingleEmail(
            $to,
            null,
            $subject,
            $parameters,
            $template,
            null,
            isset($from['address']) ? (string) $from['address'] : null,
            isset($from['sender_name']) ? (string) $from['sender_name'] : null,
            $message,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getNoReplyAddress(): array
    {
        $noReply = $this->parameters[AzineEmailExtension::NO_REPLY] ?? [];

        return [
            (string) ($noReply[AzineEmailExtension::NO_REPLY_EMAIL_ADDRESS] ?? 'no-reply@example.com'),
            (string) ($noReply[AzineEmailExtension::NO_REPLY_EMAIL_NAME] ?? 'Azine Mailer'),
        ];
    }

    private function getMailer(array $params): MailerInterface
    {
        if (
            null !== $this->immediateMailer
            && true === ($params[AzineTemplateProvider::SEND_IMMEDIATELY_FLAG] ?? false)
        ) {
            return $this->immediateMailer;
        }

        return $this->mailer;
    }

    /**
     * @return Address[]
     */
    private function normalizeAddresses(string|array|null $addresses, ?string $singleName): array
    {
        if (null === $addresses || '' === $addresses || [] === $addresses) {
            return [];
        }

        if (is_string($addresses)) {
            return [new Address($addresses, $singleName ?? '')];
        }

        $normalized = [];
        foreach ($addresses as $key => $value) {
            if (is_string($key) && !is_int($key)) {
                $normalized[] = new Address($key, is_string($value) ? $value : '');
            } elseif ($value instanceof Address) {
                $normalized[] = $value;
            } elseif (is_string($value)) {
                $normalized[] = new Address($value);
            }
        }

        return $normalized;
    }

    private function getTemplateBaseId(string $template): string
    {
        $template = str_replace('.txt.twig', '', $template);
        $template = str_replace('.html.twig', '', $template);
        $template = str_replace('.twig', '', $template);

        return $template;
    }

    private function loadTemplate(string $template): TemplateWrapper
    {
        return $this->templateCache[$template] ??= $this->twig->load($template);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     */
    private function prepareEmbeddedItems(array $params): array
    {
        $embeddedItems = [];

        array_walk_recursive($params, function (mixed $value) use (&$embeddedItems): void {
            if (!is_string($value) || !is_file($value) || !$this->templateProvider->isFileAllowed($value)) {
                return;
            }

            $cid = 'azine-'.hash('sha256', $value);
            $embeddedItems[$value] = $cid;
        });

        return $embeddedItems;
    }

    /**
     * @param array<string, string> $embeddedItems
     */
    private function attachReferencedEmbeddedItems(Email $message, array $embeddedItems, string &$htmlBody): void
    {
        foreach ($embeddedItems as $filePath => $cid) {
            $message->embedFromPath($filePath, $cid);
            $htmlBody = str_replace($filePath, 'cid:'.$cid, $htmlBody);
        }

        $message->html($htmlBody);
    }

    /**
     * @param array<int|string, mixed> $attachments
     */
    private function attachFiles(Email $message, array $attachments): void
    {
        foreach ($attachments as $key => $attachment) {
            $filePath = is_array($attachment) ? ($attachment['path'] ?? null) : $attachment;
            $name = is_array($attachment) ? ($attachment['name'] ?? null) : (is_string($key) ? $key : null);

            if (!is_string($filePath) || !is_file($filePath)) {
                throw new FileException(sprintf('Unable to attach missing file "%s".', (string) $filePath));
            }

            $message->attachFromPath($filePath, is_string($name) ? $name : null);
        }
    }

    private function appendBeforeBodyClose(string $htmlBody, string $trackingCode): string
    {
        if (false !== stripos($htmlBody, '</body>')) {
            return preg_replace('/<\/body>/i', $trackingCode.'</body>', $htmlBody, 1) ?? $htmlBody.$trackingCode;
        }

        return $htmlBody.$trackingCode;
    }

    private function createMessageId(): string
    {
        return sprintf('%s@azine-mailer.local', bin2hex(random_bytes(16)));
    }

    /**
     * @param array<string, mixed> $originalParams
     * @param array<string, mixed> $renderedParams
     * @param string[]             $failedRecipients
     */
    private function storeWebView(
        string $templateBaseId,
        array $originalParams,
        array $renderedParams,
        string $emailLocale,
        Email $message,
        array $failedRecipients,
    ): void {
        $webVariables = $this->templateProvider->makeImagePathsWebRelative($originalParams, $emailLocale);
        $sentEmail = new SentEmail();
        $sentEmail->setTemplate($templateBaseId);
        $sentEmail->setVariables($webVariables);
        $sentEmail->setToken((string) ($renderedParams[$this->templateProvider->getWebViewTokenId()] ?? SentEmail::getNewToken()));
        $sentEmail->setRecipients(array_map(
            static fn (Address $address): string => $address->getAddress(),
            $message->getTo(),
        ));
        $sentEmail->setFailedRecipients($failedRecipients);

        $manager = $this->managerRegistry->getManager();
        $manager->persist($sentEmail);
        $manager->flush();
        $manager->clear();
    }
}
