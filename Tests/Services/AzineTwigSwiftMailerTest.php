<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Services\AzineEmailTwigExtension;
use Azine\EmailBundle\Services\AzineTemplateProvider;
use Azine\EmailBundle\Services\AzineTwigMailer;
use Azine\EmailBundle\Services\SymfonyMailerTemplateProviderInterface;
use Azine\EmailBundle\Services\TemplateProviderInterface;
use Azine\EmailBundle\Tests\LocaleAwareTranslatorStub;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[AllowMockObjectsWithoutExpectations]
class AzineTwigSwiftMailerTest extends TestCase
{
    public function testSendsMultipartEmailWithLegacyServiceApi(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (Email $email): bool {
                return 'A subject' === $email->getSubject()
                    && 'to@example.com' === $email->getTo()[0]->getAddress()
                    && 'no-reply@example.com' === $email->getFrom()[0]->getAddress()
                    && 'Hello Dominik' === trim((string) $email->getTextBody())
                    && str_contains((string) $email->getHtmlBody(), '<strong>Dominik</strong>')
                    && 'present' === $email->getHeaders()->get('X-Azine-Test')?->getBodyAsString();
            }));

        $service = $this->createService($mailer);
        $message = null;

        self::assertTrue($service->sendSingleEmail(
            'to@example.com',
            'Recipient',
            'A subject',
            ['name' => 'Dominik'],
            'email.txt.twig',
            'en',
            message: $message,
        ));
        self::assertInstanceOf(Email::class, $message);
    }

    public function testImmediateFlagUsesImmediateMailer(): void
    {
        $defaultMailer = $this->createMock(MailerInterface::class);
        $defaultMailer->expects(self::never())->method('send');

        $immediateMailer = $this->createMock(MailerInterface::class);
        $immediateMailer->expects(self::once())->method('send');

        $service = $this->createService($defaultMailer, $immediateMailer, true);
        $message = null;

        self::assertTrue($service->sendSingleEmail(
            'to@example.com',
            null,
            'Immediate subject',
            ['name' => 'Dominik'],
            'email.txt.twig',
            'en',
            message: $message,
        ));
    }

    public function testTransportFailureReturnsFalseAndExposesFailedRecipient(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->method('send')
            ->willThrowException(new TransportException('Transport unavailable.'));

        $service = $this->createService($mailer);
        $failedRecipients = [];
        $message = null;

        self::assertSame(0, $service->sendEmail(
            $failedRecipients,
            'A subject',
            null,
            null,
            'to@example.com',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            ['name' => 'Dominik'],
            'email.txt.twig',
            emailLocale: 'en',
            message: $message,
        ));
        self::assertSame(['to@example.com'], $failedRecipients);
    }

    private function createService(
        MailerInterface $mailer,
        ?MailerInterface $immediateMailer = null,
        bool $sendImmediately = false,
    ): AzineTwigMailer {
        $provider = $this->createTemplateProvider($sendImmediately);
        $translator = new LocaleAwareTranslatorStub('en');

        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn(new RequestContext());

        $twig = new Environment(new ArrayLoader([
            'email.txt.twig' => <<<'TWIG'
{% block subject %}Template subject{% endblock %}
{% block body_text %}Hello {{ name }}{% endblock %}
{% block body_html %}<html><body><strong>{{ name }}</strong></body></html>{% endblock %}
TWIG,
        ]));

        return new AzineTwigMailer(
            $mailer,
            $router,
            $twig,
            $translator,
            $provider,
            $this->createMock(ManagerRegistry::class),
            null,
            new AzineEmailTwigExtension($provider, $translator),
            [
                AzineEmailExtension::NO_REPLY => [
                    AzineEmailExtension::NO_REPLY_EMAIL_ADDRESS => 'no-reply@example.com',
                    AzineEmailExtension::NO_REPLY_EMAIL_NAME => 'Azine Mailer',
                ],
                'template' => [
                    'confirmation' => 'email.txt.twig',
                    'resetting' => 'email.txt.twig',
                    'email_updating' => 'email.txt.twig',
                ],
                'from_email' => [
                    'confirmation' => ['address' => 'no-reply@example.com', 'sender_name' => 'Azine Mailer'],
                    'resetting' => ['address' => 'no-reply@example.com', 'sender_name' => 'Azine Mailer'],
                ],
            ],
            $immediateMailer,
        );
    }

    private function createTemplateProvider(bool $sendImmediately): TemplateProviderInterface
    {
        return new class($sendImmediately) implements TemplateProviderInterface, SymfonyMailerTemplateProviderInterface {
            public function __construct(private readonly bool $sendImmediately)
            {
            }

            public function addTemplateVariablesFor($template, array $contentVariables)
            {
                if ($this->sendImmediately) {
                    $contentVariables[AzineTemplateProvider::SEND_IMMEDIATELY_FLAG] = true;
                }

                return $contentVariables;
            }

            public function addTemplateSnippetsWithImagesFor($template, array $vars, $emailLocale, $forWebView = false)
            {
                return $vars;
            }

            public function addCustomHeaders($template, $message, array $params): void
            {
                if ($message instanceof Email) {
                    $this->addCustomHeadersToEmail((string) $template, $message, $params);
                }
            }

            public function addCustomHeadersToEmail(string $template, Email $message, array $params): void
            {
                $message->getHeaders()->addTextHeader('X-Azine-Test', 'present');
            }

            public function getTemplateImageDir()
            {
                return __DIR__;
            }

            public function makeImagePathsWebRelative(array $emailVars, $locale)
            {
                return $emailVars;
            }

            public function isFileAllowed($filePath)
            {
                return false;
            }

            public function getFolderFrom($key)
            {
                return false;
            }

            public function saveWebViewFor($template)
            {
                return false;
            }

            public function getWebViewTokenId()
            {
                return 'azineEmailWebViewToken';
            }

            public function getCampaignParamsFor($templateId, ?array $params = null)
            {
                return [];
            }
        };
    }
}
