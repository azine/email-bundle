<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Functional;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Services\AzineEmailTwigExtension;
use Azine\EmailBundle\Services\AzineTwigMailer;
use Azine\EmailBundle\Services\TemplateProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class EmailImagesInEmailAndWebViewTest extends TestCase
{
    public function testAllowedImageIsEmbeddedAndPersistedAsWebRelativeVariable(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'azine-email-image-');
        self::assertIsString($imagePath);
        file_put_contents($imagePath, file_get_contents(__DIR__.'/../../Resources/htmlTemplateImages/logo.png'));

        try {
            $sentMessage = null;
            $transport = $this->createMock(MailerInterface::class);
            $transport
                ->expects(self::once())
                ->method('send')
                ->willReturnCallback(static function (Email $message) use (&$sentMessage): void {
                    $sentMessage = $message;
                });

            $storedWebView = null;
            $entityManager = $this->createMock(EntityManagerInterface::class);
            $entityManager
                ->expects(self::once())
                ->method('persist')
                ->with(self::callback(static function (SentEmail $email) use (&$storedWebView): bool {
                    $storedWebView = $email;

                    return true;
                }));
            $entityManager->expects(self::once())->method('flush');
            $entityManager->expects(self::once())->method('clear');

            $registry = $this->createMock(ManagerRegistry::class);
            $registry->method('getManager')->willReturn($entityManager);

            $router = $this->createMock(RouterInterface::class);
            $router->method('getContext')->willReturn(new RequestContext());

            $translator = $this->createMock(TranslatorInterface::class);
            $translator->method('getLocale')->willReturn('en');

            $provider = new ImageWebViewTemplateProvider($imagePath);
            $twig = new Environment(new ArrayLoader([
                'test.txt.twig' => <<<'TWIG'
{% block body_text %}Image email for {{ name }}{% endblock %}
{% block body_html %}<html><body><img src="{{ image }}" alt="logo">{{ name }}</body></html>{% endblock %}
TWIG,
            ]));

            $mailer = new AzineTwigMailer(
                $transport,
                $router,
                $twig,
                $translator,
                $provider,
                $registry,
                null,
                new AzineEmailTwigExtension($provider, $translator),
                [
                    AzineEmailExtension::NO_REPLY => [
                        AzineEmailExtension::NO_REPLY_EMAIL_ADDRESS => 'no-reply@azine.me',
                        AzineEmailExtension::NO_REPLY_EMAIL_NAME => 'Azine Mailer',
                    ],
                    'template' => [
                        'confirmation' => 'test.txt.twig',
                        'resetting' => 'test.txt.twig',
                        'email_updating' => 'test.txt.twig',
                    ],
                    'from_email' => [
                        'confirmation' => ['address' => 'no-reply@azine.me', 'sender_name' => 'Azine Mailer'],
                        'resetting' => ['address' => 'no-reply@azine.me', 'sender_name' => 'Azine Mailer'],
                    ],
                ],
            );

            $message = null;
            self::assertTrue($mailer->sendSingleEmail(
                'recipient@example.com',
                'Recipient',
                'Embedded image test',
                ['name' => 'Dominik', 'image' => $imagePath],
                'test.txt.twig',
                'en',
                message: $message,
            ));

            self::assertSame($sentMessage, $message);
            self::assertInstanceOf(Email::class, $sentMessage);
            self::assertStringContainsString('src="cid:azine-', (string) $sentMessage->getHtmlBody());
            self::assertCount(1, $sentMessage->getAttachments());

            self::assertInstanceOf(SentEmail::class, $storedWebView);
            self::assertSame(['recipient@example.com'], $storedWebView->getRecipients());
            self::assertSame('/email/web-images/logo.png', $storedWebView->getVariables()['image']);
            self::assertNotEmpty($storedWebView->getToken());
        } finally {
            @unlink($imagePath);
        }
    }
}

final class ImageWebViewTemplateProvider implements TemplateProviderInterface
{
    public function __construct(private readonly string $imagePath)
    {
    }

    public function addTemplateVariablesFor($template, array $contentVariables)
    {
        return $contentVariables;
    }

    public function addTemplateSnippetsWithImagesFor($template, array $vars, $emailLocale, $forWebView = false)
    {
        return $vars;
    }

    public function addCustomHeaders($template, $message, array $params): void
    {
    }

    public function getTemplateImageDir()
    {
        return dirname($this->imagePath).DIRECTORY_SEPARATOR;
    }

    public function makeImagePathsWebRelative(array $emailVars, $locale)
    {
        array_walk_recursive($emailVars, function (&$value): void {
            if ($value === $this->imagePath) {
                $value = '/email/web-images/logo.png';
            }
        });

        return $emailVars;
    }

    public function isFileAllowed($filePath)
    {
        return realpath((string) $filePath) === realpath($this->imagePath);
    }

    public function getFolderFrom($key)
    {
        return false;
    }

    public function saveWebViewFor($template)
    {
        return true;
    }

    public function getWebViewTokenId()
    {
        return 'azineEmailWebViewToken';
    }

    public function getCampaignParamsFor($templateId, array $params = null)
    {
        return [];
    }
}
