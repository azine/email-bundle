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

        $previousLocale = $this->translator->getLocale();
        $emailLocale = $emailLocale ?: $previousLocale;
        $routerContext = $this->router->getContext();
        $previousRouteLocale = $routerContext->getParameter('_locale');

        if (method_exists($this->translator, 'setLocale')) {
            $this->translator->setLocale($emailLocale);
        }
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
            if (method_exists($this->translator, 'setLocale')) {
                $this->translator->setLocale($previousLocale);
            }
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
    public function sendUpdateEmailConfirmation(
        UserInterface $user,
        string $confirmationUrl,
        string $toEmail,
    ): void {
        $template = (string) $this->parameters['template']['email_updating'];
        $this->sendAccountMessage(
            $template,
            ['user' => $user, 'confirmationUrl' => $confirmationUrl],
            $this->parameters['from_email']['confirmation'],
            $toEmail,
        );
    }

    /**
     * @param array{address?: string, sender_name?: string}|string $fromEmail
     */
    private function sendAccountMessage(string $template, array $context, array|string $fromEmail, string $toEmail): void
    {
        $twigTemplate = $this->loadTemplate($template);
        $subject = trim($twigTemplate->renderBlock('subject', $context));
        [$fromAddress, $fromName] = $this->normalizeFromConfiguration($fromEmail);
        $message = null;

        if (!$this->sendSingleEmail(
            $toEmail,
            null,
            $subject,
            $context,
            $template,
            $this->translator->getLocale(),
            $fromAddress,
            $fromName,
            $message,
        )) {
            throw new \RuntimeException(sprintf('Unable to send account email to "%s".', $toEmail));
        }
    }

    private function loadTemplate(string $template): TemplateWrapper
    {
        return $this->templateCache[$template] ??= $this->twig->load($template);
    }

    private function getTemplateBaseId(string $template): string
    {
        return preg_replace('/\.(?:txt|html)\.twig$/', '', $template) ?? $template;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getNoReplyAddress(): array
    {
        $config = $this->parameters[AzineEmailExtension::NO_REPLY] ?? $this->parameters['no_reply'] ?? [];

        return [
            (string) ($config[AzineEmailExtension::NO_REPLY_EMAIL_ADDRESS] ?? $config['email'] ?? 'no-reply@example.com'),
            (string) ($config[AzineEmailExtension::NO_REPLY_EMAIL_NAME] ?? $config['name'] ?? 'Notification service'),
        ];
    }

    /**
     * @param array{address?: string, sender_name?: string}|string $fromEmail
     *
     * @return array{0: string, 1: string}
     */
    private function normalizeFromConfiguration(array|string $fromEmail): array
    {
        if (is_string($fromEmail)) {
            return [$fromEmail, ''];
        }

        return [
            (string) ($fromEmail['address'] ?? array_key_first($fromEmail) ?? ''),
            (string) ($fromEmail['sender_name'] ?? (is_string(reset($fromEmail)) ? reset($fromEmail) : '')),
        ];
    }

    /**
     * @return list<Address>
     */
    private function normalizeAddresses(string|array|null $addresses, ?string $name = null): array
    {
        if (null === $addresses || '' === $addresses || [] === $addresses) {
            return [];
        }

        if (is_string($addresses)) {
            return [new Address($addresses, $name ?? '')];
        }

        $normalized = [];
        foreach ($addresses as $email => $displayName) {
            if (is_int($email)) {
                $normalized[] = new Address((string) $displayName);
            } else {
                $normalized[] = new Address((string) $email, (string) $displayName);
            }
        }

        return $normalized;
    }

    private function createMessageId(): string
    {
        $host = preg_replace('/[^a-z0-9.-]/i', '', gethostname() ?: 'localhost') ?: 'localhost';

        return bin2hex(random_bytes(16)).'@'.$host;
    }

    private function appendBeforeBodyClose(string $html, string $addition): string
    {
        $position = stripos($html, '</body>');

        return false === $position
            ? $html.$addition
            : substr_replace($html, $addition, $position, 0);
    }

    /**
     * Replaces allowed image file paths and generated GD images with stable cid: references.
     *
     * @return array<string, array{cid: string, path?: string, data?: string, contentType?: string}>
     */
    private function prepareEmbeddedItems(array &$params): array
    {
        $embeddedItems = [];
        $this->walkEmbeddedItems($params, $embeddedItems);

        return $embeddedItems;
    }

    /**
     * @param array<string, array{cid: string, path?: string, data?: string, contentType?: string}> $embeddedItems
     */
    private function walkEmbeddedItems(array &$params, array &$embeddedItems): void
    {
        foreach ($params as $key => &$value) {
            if (is_array($value)) {
                $this->walkEmbeddedItems($value, $embeddedItems);
                continue;
            }

            if (is_string($value) && is_file($value) && $this->templateProvider->isFileAllowed($value)) {
                $path = realpath($value);
                if (false === $path) {
                    continue;
                }

                $cid = 'azine-'.sha1($path);
                $embeddedItems[$cid] = ['cid' => $cid, 'path' => $path];
                $value = 'cid:'.$cid;
                continue;
            }

            $isGdImage = class_exists(\GdImage::class) && $value instanceof \GdImage;
            $isLegacyGdResource = is_resource($value) && str_starts_with(strtolower(get_resource_type($value)), 'gd');
            if (!$isGdImage && !$isLegacyGdResource) {
                continue;
            }

            ob_start();
            imagepng($value);
            $data = (string) ob_get_clean();
            $cid = 'azine-generated-'.sha1($data);
            $embeddedItems[$cid] = [
                'cid' => $cid,
                'data' => $data,
                'contentType' => 'image/png',
            ];
            $value = 'cid:'.$cid;
        }
        unset($value);
    }

    /**
     * @param array<string, array{cid: string, path?: string, data?: string, contentType?: string}> $embeddedItems
     */
    private function attachReferencedEmbeddedItems(Email $message, array $embeddedItems, string $htmlBody): void
    {
        foreach ($embeddedItems as $item) {
            if (!str_contains($htmlBody, 'cid:'.$item['cid'])) {
                continue;
            }

            if (isset($item['path'])) {
                $message->embedFromPath($item['path'], $item['cid']);
                continue;
            }

            if (isset($item['data'])) {
                $message->embed($item['data'], $item['cid'], $item['contentType'] ?? null);
            }
        }
    }

    private function attachFiles(Email $message, array $attachments): void
    {
        foreach ($attachments as $fileName => $file) {
            if (is_string($file)) {
                if (!is_file($file)) {
                    throw new FileException('File not found: '.$file);
                }

                $message->attachFromPath(
                    $file,
                    strlen((string) $fileName) >= 5 ? (string) $fileName : null,
                );
                continue;
            }

            $message->attach((string) $file, (string) $fileName);
        }
    }

    private function getMailer(array $params): MailerInterface
    {
        if (
            null !== $this->immediateMailer
            && !empty($params[AzineTemplateProvider::SEND_IMMEDIATELY_FLAG])
        ) {
            return $this->immediateMailer;
        }

        return $this->mailer;
    }

    private function storeWebView(
        string $templateBaseId,
        array $webViewParams,
        array $renderedParams,
        string $emailLocale,
        Email $message,
        array $failedRecipients,
    ): void {
        $tokenId = $this->templateProvider->getWebViewTokenId();
        if (!array_key_exists($tokenId, $renderedParams)) {
            return;
        }

        $webViewParams = $this->templateProvider->addTemplateVariablesFor($templateBaseId, $webViewParams);
        $webViewParams = $this->templateProvider->makeImagePathsWebRelative($webViewParams, $emailLocale);
        $webViewParams = $this->templateProvider->addTemplateSnippetsWithImagesFor(
            $templateBaseId,
            $webViewParams,
            $emailLocale,
            true,
        );

        $recipients = array_map(
            static fn (Address $address): string => $address->getAddress(),
            $message->getTo(),
        );

        $sentEmail = new SentEmail();
        $sentEmail->setToken((string) $renderedParams[$tokenId]);
        $sentEmail->setTemplate($templateBaseId);
        $sentEmail->setSent(new \DateTime());
        $sentEmail->setVariables($webViewParams);
        $sentEmail->setRecipients(array_values(array_diff($recipients, $failedRecipients)));

        $entityManager = $this->managerRegistry->getManager();
        $entityManager->persist($sentEmail);
        $entityManager->flush();
        $entityManager->clear();
    }
}
