<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\DependencyInjection;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AzineEmailExtensionTest extends TestCase
{
    public function testDefaultConfigurationLoadsUsableServiceAliases(): void
    {
        $container = $this->load([]);

        self::assertSame(
            'Acme\\SomeBundle\\Entity\\User',
            $container->getParameter('azine_email_recipient_class'),
        );
        self::assertSame(
            'azine_email.example.template_provider',
            (string) $container->getAlias('azine_email_template_provider'),
        );
        self::assertSame(
            'azine_email.default.template_twig_mailer',
            (string) $container->getAlias('azine_email_template_twig_mailer'),
        );
        self::assertSame(
            'azine_email_template_twig_mailer',
            (string) $container->getAlias('azine_email_template_twig_swift_mailer'),
        );
        self::assertTrue($container->hasDefinition('azine_email.default.template_twig_mailer'));
        self::assertTrue($container->hasDefinition('azine_email.command_lock_factory'));
    }

    public function testMinimalConfigurationOverridesRequiredApplicationValues(): void
    {
        $container = $this->load([
            'recipient_class' => 'Azine\\PlatformBundle\\Entity\\User',
            'template_provider' => 'azine_platform.emailtemplateprovider',
            'no_reply' => [
                'email' => 'no-reply@azine.me',
                'name' => 'azine.me notification daemon',
            ],
        ]);

        self::assertSame(
            'Azine\\PlatformBundle\\Entity\\User',
            $container->getParameter('azine_email_recipient_class'),
        );
        self::assertSame(
            'azine_platform.emailtemplateprovider',
            (string) $container->getAlias('azine_email_template_provider'),
        );
        self::assertSame([
            'email' => 'no-reply@azine.me',
            'name' => 'azine.me notification daemon',
        ], $container->getParameter('azine_email_no_reply'));
    }

    public function testCanonicalMailerOptionAndImmediateMailerAreWired(): void
    {
        $container = $this->load([
            'template_twig_mailer' => 'app.custom_mailer',
            'immediate_mailer_service' => 'app.immediate_mailer',
        ]);

        self::assertSame(
            'app.custom_mailer',
            (string) $container->getAlias('azine_email_template_twig_mailer'),
        );
        self::assertSame(
            'azine_email_template_twig_mailer',
            (string) $container->getAlias('azine_email_template_twig_swift_mailer'),
        );
        self::assertSame(
            'app.immediate_mailer',
            (string) $container->getAlias('azine_email_immediate_mailer_service'),
        );
    }

    public function testDeprecatedSwiftmailerOptionStillFeedsCanonicalAlias(): void
    {
        $container = $this->load([
            'template_twig_mailer' => 'azine_email.default.template_twig_mailer',
            'template_twig_swift_mailer' => 'app.legacy_named_mailer',
        ]);

        self::assertSame(
            'app.legacy_named_mailer',
            (string) $container->getAlias('azine_email_template_twig_mailer'),
        );
        self::assertSame(
            'azine_email_template_twig_mailer',
            (string) $container->getAlias('azine_email_template_twig_swift_mailer'),
        );
    }

    public function testFullCustomConfigurationIsRetained(): void
    {
        $container = $this->load([
            'recipient_class' => 'TestRecipientClass',
            'recipient_newsletter_field' => 'some_field',
            'template_provider' => 'TestTemplateProvider',
            'notifier_service' => 'TestNotifierService',
            'recipient_provider' => 'TestRecipientService',
            'template_twig_mailer' => 'TestTwigMailer',
            'immediate_mailer_service' => 'TestImmediateMailer',
            'no_reply' => [
                'email' => 'test@email.com',
                'name' => 'test name',
            ],
            'image_dir' => '/tmp',
            'allowed_images_folders' => ['/tmp'],
            'newsletter' => [
                'interval' => 7,
                'send_time' => '09:30',
            ],
            'web_view_retention' => 45,
        ]);

        self::assertSame('TestRecipientClass', $container->getParameter('azine_email_recipient_class'));
        self::assertSame('some_field', $container->getParameter('azine_email_recipient_newsletter_field'));
        self::assertSame('/tmp', $container->getParameter('azine_email_image_dir'));
        self::assertSame(['/tmp'], $container->getParameter('azine_email_allowed_images_folders'));
        self::assertSame(7, $container->getParameter('azine_email_newsletter_interval'));
        self::assertSame('09:30', $container->getParameter('azine_email_newsletter_send_time'));
        self::assertSame(45, $container->getParameter('azine_email_web_view_retention'));
        self::assertSame('testtemplateprovider', strtolower((string) $container->getAlias('azine_email_template_provider')));
        self::assertSame('testnotifierservice', strtolower((string) $container->getAlias('azine_email_notifier_service')));
        self::assertSame('testrecipientservice', strtolower((string) $container->getAlias('azine_email_recipient_provider')));
        self::assertSame('testtwigmailer', strtolower((string) $container->getAlias('azine_email_template_twig_mailer')));
        self::assertSame('testimmediatemailer', strtolower((string) $container->getAlias('azine_email_immediate_mailer_service')));
    }

    public function testRejectsInvalidNewsletterTime(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->load([
            'newsletter' => ['send_time' => '25:99'],
        ]);
    }

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new AzineEmailExtension())->load([$config], $container);

        return $container;
    }
}
