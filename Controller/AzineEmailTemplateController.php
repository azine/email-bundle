<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Controller;

use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Services\AzineEmailTwigExtension;
use Azine\EmailBundle\Services\EmailOpenTrackingCodeBuilderInterface;
use Azine\EmailBundle\Services\SpamCheckService;
use Azine\EmailBundle\Services\TemplateProviderInterface;
use Azine\EmailBundle\Services\TemplateTwigMailerInterface;
use Azine\EmailBundle\Services\WebViewServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AzineEmailTemplateController extends AbstractController
{
    public function __construct(
        private readonly WebViewServiceInterface $webViewService,
        private readonly TemplateProviderInterface $templateProvider,
        private readonly TemplateTwigMailerInterface $mailer,
        private readonly SpamCheckService $spamCheckService,
        private readonly Environment $twig,
        private readonly AzineEmailTwigExtension $emailTwigExtension,
        private readonly ManagerRegistry $managerRegistry,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TranslatorInterface $translator,
        private readonly RouterInterface $router,
        private readonly ?EmailOpenTrackingCodeBuilderInterface $emailOpenTrackingCodeBuilder,
        private readonly array $noReply,
        private readonly int $webViewRetentionDays,
    ) {
    }

    public function indexAction(Request $request): Response
    {
        return $this->renderTemplate('@AzineEmail/Webview/index.html.twig', [
            'customEmail' => $request->query->getString('customEmail', 'custom@email.com'),
            'templates' => $this->webViewService->getTemplatesForWebPreView(),
            'emails' => $this->webViewService->getTestMailAccounts(),
        ]);
    }

    public function webPreViewAction(Request $request, string $template, ?string $format = null): Response
    {
        $format = 'txt' === $format ? 'txt' : 'html';
        $template = urldecode($template);
        $locale = $request->getLocale();
        $requestVariables = $request->query->all();

        $emailVariables = $this->webViewService->getDummyVarsFor(
            $template,
            $locale,
            $requestVariables,
        );
        $emailVariables = array_merge($emailVariables, $requestVariables);
        $emailVariables = $this->templateProvider->addTemplateVariablesFor($template, $emailVariables);

        $emailVariables['fromEmail'] ??= (string) ($this->noReply['email'] ?? '');
        $emailVariables['fromName'] ??= (string) ($this->noReply['name'] ?? '');
        $emailVariables['sendMailAccountAddress'] ??= $emailVariables['fromEmail'];
        $emailVariables['sendMailAccountName'] ??= $emailVariables['fromName'];
        $emailVariables['emailLocale'] = $locale;

        $emailVariables = $this->templateProvider->makeImagePathsWebRelative($emailVariables, $locale);
        $emailVariables = $this->templateProvider->addTemplateSnippetsWithImagesFor(
            $template,
            $emailVariables,
            $locale,
        );

        $content = $this->twig->render($this->templateFile($template, $format), $emailVariables);
        $campaignParameters = $this->templateProvider->getCampaignParamsFor($template, $emailVariables);
        if ([] !== $campaignParameters) {
            $campaignParameters['utm_medium'] = 'webPreview';
            $content = $this->emailTwigExtension->addCampaignParamsToAllUrls($content, $campaignParameters);

            if ('html' === $format && null !== $this->emailOpenTrackingCodeBuilder) {
                $trackingCode = $this->emailOpenTrackingCodeBuilder->getTrackingImgCode(
                    $template,
                    $campaignParameters,
                    $emailVariables,
                    'dummy',
                    'dummy@from.email.com',
                    null,
                    null,
                );
                if (is_string($trackingCode) && '' !== $trackingCode) {
                    $trackingCode = str_replace('://', '://webview-dummy-domain.', $trackingCode);
                    $content = $this->appendBeforeClosingTag($content, $trackingCode, '</html>');
                }
            }
        }

        if ('txt' === $format) {
            $textEnd = stripos($content, '<!doctype');
            if (false !== $textEnd) {
                $content = substr($content, 0, $textEnd);
            }

            return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return new Response($content);
    }

    public function webViewAction(Request $request, string $token): Response
    {
        $sentEmail = $this->getSentEmailForToken($token);
        if (!$sentEmail instanceof SentEmail) {
            return $this->renderTemplate(
                '@AzineEmail/Webview/mail.not.available.html.twig',
                ['days' => $this->webViewRetentionDays],
                Response::HTTP_NOT_FOUND,
            );
        }

        if (!$this->userIsAllowedToSeeThisMail($sentEmail)) {
            throw new AccessDeniedException(
                $this->translator->trans('web.pre.view.test.mail.access.denied'),
            );
        }

        $template = (string) $sentEmail->getTemplate();
        $emailVariables = $sentEmail->getVariables();
        $this->reAttachAllEntities($emailVariables);
        unset($emailVariables[$this->templateProvider->getWebViewTokenId()]);

        $content = $this->twig->render($this->templateFile($template, 'html'), $emailVariables);
        $campaignParameters = $this->templateProvider->getCampaignParamsFor($template, $emailVariables);
        if ([] !== $campaignParameters) {
            $content = $this->emailTwigExtension->addCampaignParamsToAllUrls($content, $campaignParameters);
        }

        return new Response($content);
    }

    public function serveImageAction(Request $request, string $folderKey, string $filename): BinaryFileResponse
    {
        $folder = $this->templateProvider->getFolderFrom($folderKey);
        if (false === $folder) {
            throw new FileNotFoundException($filename);
        }

        $baseFolder = realpath((string) $folder);
        $fullPath = realpath(rtrim((string) $folder, '/').'/'.urldecode($filename));
        if (
            false === $baseFolder
            || false === $fullPath
            || !str_starts_with($fullPath, rtrim($baseFolder, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || !is_file($fullPath)
        ) {
            throw new FileNotFoundException($filename);
        }

        $response = new BinaryFileResponse($fullPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        $mimeType = mime_content_type($fullPath);
        if (is_string($mimeType)) {
            $response->headers->set('Content-Type', $mimeType);
        }

        return $response;
    }

    public function sendTestEmailAction(Request $request, string $template, string $email): RedirectResponse
    {
        $locale = $request->getLocale();
        $template = urldecode($template);
        $emailVariables = $this->webViewService->getDummyVarsFor($template, $locale);
        $recipients = $this->parseAddresses($email);
        $message = new Email();

        $sent = $this->mailer->sendSingleEmail(
            $recipients,
            null,
            (string) ($emailVariables['subject'] ?? 'Test email'),
            $emailVariables,
            $this->templateFile($template, 'txt'),
            $locale,
            (string) ($emailVariables['sendMailAccountAddress'] ?? $this->noReply['email'] ?? ''),
            (string) ($emailVariables['sendMailAccountName'] ?? $this->noReply['name'] ?? '').' (Test)',
            $message,
        );

        $flashBag = $request->getSession()->getFlashBag();
        $spamReport = $this->getSpamIndexReportForSwiftMessage($message);
        $spamInfo = $this->formatSpamReport($spamReport);
        if (null !== $spamInfo) {
            [$level, $messageText] = $spamInfo;
            $flashBag->add($level, $messageText);
        }

        $translationKey = $sent
            ? 'web.pre.view.test.mail.sent.for.%template%.to.%email%'
            : 'web.pre.view.test.mail.failed.for.%template%.to.%email%';
        $flashBag->add($sent ? 'info' : 'warn', $this->translator->trans($translationKey, [
            '%template%' => $template,
            '%email%' => $email,
        ]));

        return new RedirectResponse($this->router->generate('azine_email_template_index', [
            'customEmail' => $email,
        ]));
    }

    /**
     * The historical method name is retained for application compatibility;
     * the message is now a Symfony Mime RawMessage rather than Swift_Message.
     */
    public function getSpamIndexReportForSwiftMessage(RawMessage $message, string $report = 'long'): array
    {
        return $this->spamCheckService->checkMessage($message, $report);
    }

    public function checkSpamScoreOfSentEmailAction(Request $request): JsonResponse
    {
        $messageSource = $request->request->getString(
            'emailSource',
            $request->query->getString('emailSource'),
        );
        $spamReport = $this->spamCheckService->checkRawMessage($messageSource);
        $formatted = $this->formatSpamReport($spamReport);

        return new JsonResponse([
            'result' => null === $formatted ? '' : $formatted[1],
        ]);
    }

    private function getSentEmailForToken(string $token): ?SentEmail
    {
        $sentEmail = $this->managerRegistry
            ->getManager()
            ->getRepository(SentEmail::class)
            ->findOneBy(['token' => $token]);

        return $sentEmail instanceof SentEmail ? $sentEmail : null;
    }

    private function userIsAllowedToSeeThisMail(SentEmail $mail): bool
    {
        $recipients = $mail->getRecipients();
        if (null === $recipients) {
            return true;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!is_object($user)) {
            return false;
        }

        if (method_exists($user, 'getEmail') && in_array($user->getEmail(), $recipients, true)) {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        return method_exists($user, 'getRoles') && in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    private function reAttachAllEntities(array &$variables): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->managerRegistry->getManager();

        foreach ($variables as $key => &$value) {
            if (is_array($value)) {
                $this->reAttachAllEntities($value);
                continue;
            }

            if (!is_object($value) || !method_exists($value, 'getId')) {
                continue;
            }

            $identifier = $value->getId();
            if (null === $identifier) {
                continue;
            }

            $managedEntity = $entityManager->find($value::class, $identifier);
            if (null !== $managedEntity) {
                $variables[$key] = $managedEntity;
            }
        }
        unset($value);
    }

    /**
     * @return array<string, string>
     */
    private function parseAddresses(string $email): array
    {
        $recipients = [];
        foreach (mailparse_rfc822_parse_addresses($email) as $parsedAddress) {
            $address = (string) ($parsedAddress['address'] ?? '');
            if ('' === $address || false === filter_var($address, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $recipients[$address] = (string) ($parsedAddress['display'] ?? 'Test-Mail-Recipient');
        }

        if ([] === $recipients) {
            throw new \InvalidArgumentException(sprintf('No valid email address was found in "%s".', $email));
        }

        return $recipients;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function formatSpamReport(array $spamReport): ?array
    {
        if (Response::HTTP_OK === ($spamReport['curlHttpCode'] ?? null) && true === ($spamReport['success'] ?? false)) {
            $score = (float) ($spamReport['score'] ?? 10);
            $info = sprintf("SpamScore: %s! \n%s", $score, (string) ($spamReport['report'] ?? ''));

            return [
                $score <= 2 ? 'info' : ($score < 5 ? 'warn' : 'error'),
                $info,
            ];
        }

        $info = sprintf(
            "Getting the spam-info failed.\nHttpCode: %s\nSpamReportMsg: %s",
            (string) ($spamReport['curlHttpCode'] ?? '-'),
            (string) ($spamReport['message'] ?? '-'),
        );
        if (isset($spamReport['curlError'])) {
            $info .= "\ncURL-Error: ".(string) $spamReport['curlError'];
        }

        return ['error', $info];
    }

    private function renderTemplate(string $template, array $parameters, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render($template, $parameters), $status);
    }

    private function templateFile(string $templateBase, string $format): string
    {
        return $this->normalizeTemplateBase($templateBase).'.'.$format.'.twig';
    }

    private function normalizeTemplateBase(string $template): string
    {
        if (str_starts_with($template, '@')) {
            return $template;
        }

        if (!str_contains($template, ':')) {
            return $template;
        }

        $parts = explode(':', $template);
        $bundle = preg_replace('/Bundle$/', '', array_shift($parts) ?? '') ?: '';
        $path = implode('/', array_values(array_filter($parts, static fn (string $part): bool => '' !== $part)));

        return '@'.$bundle.('/' === substr($bundle, -1) || '' === $path ? '' : '/').$path;
    }

    private function appendBeforeClosingTag(string $content, string $addition, string $closingTag): string
    {
        $position = stripos($content, $closingTag);

        return false === $position
            ? $content.$addition
            : substr_replace($content, $addition, $position, 0);
    }
}
