<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Controller;

use Azine\EmailBundle\Controller\AzineEmailTemplateController;
use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Services\AzineEmailTwigExtension;
use Azine\EmailBundle\Services\SpamCheckService;
use Azine\EmailBundle\Services\TemplateProviderInterface;
use Azine\EmailBundle\Services\TemplateTwigMailerInterface;
use Azine\EmailBundle\Services\WebViewServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class AzineEmailTemplateControllerTest extends TestCase
{
    public function testIndexActionRendersTemplatesAndAddresses(): void
    {
        $webViewService = $this->createMock(WebViewServiceInterface::class);
        $webViewService->method('getTemplatesForWebPreView')->willReturn([['description' => 'Newsletter']]);
        $webViewService->method('getTestMailAccounts')->willReturn([['accountEmail' => 'test@example.com']]);

        $controller = $this->createController(
            webViewService: $webViewService,
            twigTemplates: [
                '@AzineEmail/Webview/index.html.twig' => '{{ customEmail }}|{{ templates[0].description }}|{{ emails[0].accountEmail }}',
            ],
        );

        $response = $controller->indexAction(new Request(['customEmail' => 'custom@example.com']));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            'custom@example.com|Newsletter|test@example.com',
            $response->getContent(),
        );
    }

    public function testHtmlPreviewSupportsLegacyBundleTemplateNotation(): void
    {
        $webViewService = $this->createMock(WebViewServiceInterface::class);
        $webViewService
            ->expects(self::once())
            ->method('getDummyVarsFor')
            ->with('AzineEmailBundle::preview', 'de', ['name' => 'Request value'])
            ->willReturn(['name' => 'Dummy value']);

        $templateProvider = $this->createTemplateProviderMock();
        $templateProvider->method('addTemplateVariablesFor')->willReturnCallback(
            static fn (string $template, array $variables): array => $variables,
        );
        $templateProvider->method('makeImagePathsWebRelative')->willReturnArgument(0);
        $templateProvider->method('addTemplateSnippetsWithImagesFor')->willReturnArgument(1);
        $templateProvider->method('getCampaignParamsFor')->willReturn([]);

        $controller = $this->createController(
            webViewService: $webViewService,
            templateProvider: $templateProvider,
            twigTemplates: [
                '@AzineEmail/preview.html.twig' => '<html>{{ name }}|{{ fromEmail }}|{{ emailLocale }}</html>',
            ],
        );
        $request = new Request(['name' => 'Request value']);
        $request->setLocale('de');

        $response = $controller->webPreViewAction($request, 'AzineEmailBundle::preview', 'html');

        self::assertSame(
            '<html>Request value|no-reply@azine.me|de</html>',
            $response->getContent(),
        );
    }

    public function testTextPreviewUsesPlainTextContentType(): void
    {
        $webViewService = $this->createMock(WebViewServiceInterface::class);
        $webViewService->method('getDummyVarsFor')->willReturn(['name' => 'Dominik']);

        $templateProvider = $this->createTemplateProviderMock();
        $templateProvider->method('addTemplateVariablesFor')->willReturnArgument(1);
        $templateProvider->method('makeImagePathsWebRelative')->willReturnArgument(0);
        $templateProvider->method('addTemplateSnippetsWithImagesFor')->willReturnArgument(1);
        $templateProvider->method('getCampaignParamsFor')->willReturn([]);

        $controller = $this->createController(
            webViewService: $webViewService,
            templateProvider: $templateProvider,
            twigTemplates: ['@App/Email/message.txt.twig' => 'Hello {{ name }}'],
        );

        $response = $controller->webPreViewAction(new Request(), '@App/Email/message', 'txt');

        self::assertSame('Hello Dominik', $response->getContent());
        self::assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
    }

    public function testPublicStoredEmailCanBeViewed(): void
    {
        $sentEmail = (new SentEmail())
            ->setToken('public-token')
            ->setRecipients(null)
            ->setTemplate('@App/Email/stored')
            ->setVariables(['name' => 'Stored']);

        $templateProvider = $this->createTemplateProviderMock();
        $templateProvider->method('getWebViewTokenId')->willReturn('webViewToken');
        $templateProvider->method('getCampaignParamsFor')->willReturn([]);

        $controller = $this->createController(
            templateProvider: $templateProvider,
            sentEmail: $sentEmail,
            twigTemplates: ['@App/Email/stored.html.twig' => 'Stored email: {{ name }}'],
        );

        $response = $controller->webViewAction(new Request(), 'public-token');

        self::assertSame('Stored email: Stored', $response->getContent());
    }

    public function testPrivateStoredEmailRejectsUnrelatedUser(): void
    {
        $sentEmail = (new SentEmail())
            ->setToken('private-token')
            ->setRecipients(['recipient@example.com'])
            ->setTemplate('@App/Email/stored')
            ->setVariables([]);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(new class {
            public function getEmail(): string
            {
                return 'other@example.com';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }
        });
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $this->expectException(AccessDeniedException::class);

        $this->createController(
            sentEmail: $sentEmail,
            tokenStorage: $tokenStorage,
            twigTemplates: ['@App/Email/stored.html.twig' => 'private'],
        )->webViewAction(new Request(), 'private-token');
    }

    public function testUnavailableStoredEmailReturns404(): void
    {
        $controller = $this->createController(twigTemplates: [
            '@AzineEmail/Webview/mail.not.available.html.twig' => 'Unavailable after {{ days }} days',
        ]);

        $response = $controller->webViewAction(new Request(), 'missing');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('Unavailable after 90 days', $response->getContent());
    }

    public function testServeImageReturnsInlineFileAndRejectsTraversal(): void
    {
        $folder = sys_get_temp_dir().'/azine-email-test-'.bin2hex(random_bytes(4));
        mkdir($folder);
        file_put_contents($folder.'/logo.png', 'image-data');

        try {
            $templateProvider = $this->createTemplateProviderMock();
            $templateProvider->method('getFolderFrom')->with('templates')->willReturn($folder.'/');
            $controller = $this->createController(templateProvider: $templateProvider);

            $response = $controller->serveImageAction(new Request(), 'templates', 'logo.png');
            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
            self::assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));

            $this->expectException(\Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException::class);
            $controller->serveImageAction(new Request(), 'templates', '../secret.txt');
        } finally {
            @unlink($folder.'/logo.png');
            @rmdir($folder);
        }
    }

    public function testSendTestEmailAddsSpamAndDeliveryFlashes(): void
    {
        $webViewService = $this->createMock(WebViewServiceInterface::class);
        $webViewService->method('getDummyVarsFor')->willReturn([
            'subject' => 'Test subject',
            'sendMailAccountAddress' => 'sender@example.com',
            'sendMailAccountName' => 'Sender',
        ]);

        $mailer = $this->createMock(TemplateTwigMailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('sendSingleEmail')
            ->with(
                ['recipient@example.com' => 'Test-Mail-Recipient'],
                null,
                'Test subject',
                self::isType('array'),
                '@App/Email/test.txt.twig',
                'en',
                'sender@example.com',
                'Sender (Test)',
                self::isInstanceOf(Email::class),
            )
            ->willReturn(true);

        $spamCheck = $this->createMock(SpamCheckService::class);
        $spamCheck->method('checkMessage')->willReturn([
            'success' => true,
            'curlHttpCode' => 200,
            'score' => 1.2,
            'report' => 'Looks good',
            'message' => '-',
        ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Mail sent');
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/email/templates?customEmail=recipient@example.com');

        $controller = $this->createController(
            webViewService: $webViewService,
            mailer: $mailer,
            spamCheckService: $spamCheck,
            translator: $translator,
            router: $router,
        );
        $request = new Request();
        $request->setLocale('en');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->sendTestEmailAction(
            $request,
            '@App/Email/test',
            'recipient@example.com',
        );

        self::assertSame('/email/templates?customEmail=recipient@example.com', $response->getTargetUrl());
        self::assertCount(2, $request->getSession()->getFlashBag()->all());
    }

    public function testSpamScoreAjaxReturnsFormattedReport(): void
    {
        $spamCheck = $this->createMock(SpamCheckService::class);
        $spamCheck
            ->expects(self::once())
            ->method('checkRawMessage')
            ->with('raw message')
            ->willReturn([
                'success' => true,
                'curlHttpCode' => 200,
                'score' => 3.1,
                'report' => 'Some warnings',
                'message' => '-',
            ]);

        $request = new Request([], ['emailSource' => 'raw message']);
        $response = $this->createController(spamCheckService: $spamCheck)
            ->checkSpamScoreOfSentEmailAction($request);

        self::assertStringContainsString('SpamScore: 3.1', (string) $response->getContent());
    }

    private function createController(
        ?WebViewServiceInterface $webViewService = null,
        ?TemplateProviderInterface $templateProvider = null,
        ?TemplateTwigMailerInterface $mailer = null,
        ?SpamCheckService $spamCheckService = null,
        ?TokenStorageInterface $tokenStorage = null,
        ?TranslatorInterface $translator = null,
        ?RouterInterface $router = null,
        ?SentEmail $sentEmail = null,
        array $twigTemplates = [],
    ): AzineEmailTemplateController {
        $webViewService ??= $this->createMock(WebViewServiceInterface::class);
        $templateProvider ??= $this->createTemplateProviderMock();
        $mailer ??= $this->createMock(TemplateTwigMailerInterface::class);
        $spamCheckService ??= $this->createMock(SpamCheckService::class);
        $tokenStorage ??= $this->createMock(TokenStorageInterface::class);
        $translator ??= $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $router ??= $this->createMock(RouterInterface::class);

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repository->method('findOneBy')->willReturn($sentEmail);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);

        $twig = new Environment(new ArrayLoader($twigTemplates));
        $twigExtension = $this->createMock(AzineEmailTwigExtension::class);
        $twigExtension
            ->method('addCampaignParamsToAllUrls')
            ->willReturnArgument(0);

        return new AzineEmailTemplateController(
            $webViewService,
            $templateProvider,
            $mailer,
            $spamCheckService,
            $twig,
            $twigExtension,
            $registry,
            $tokenStorage,
            $translator,
            $router,
            null,
            ['email' => 'no-reply@azine.me', 'name' => 'Azine Mailer'],
            90,
        );
    }

    private function createTemplateProviderMock(): TemplateProviderInterface
    {
        $provider = $this->createMock(TemplateProviderInterface::class);
        $provider->method('getWebViewTokenId')->willReturn('webViewToken');
        $provider->method('getCampaignParamsFor')->willReturn([]);

        return $provider;
    }
}
