<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Services\AzineTemplateProvider;
use Azine\EmailBundle\Services\SymfonyMailerTemplateProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class AzineTemplateProviderTest extends TestCase
{
    public function testAddTemplateVariablesFor(): void
    {
        $provider = $this->createProvider();
        $contentVariables = ['testVar' => 'testValue'];

        $resetVariables = $provider->addTemplateVariablesFor(
            AzineTemplateProvider::FOS_USER_PWD_RESETTING_TEMPLATE,
            $contentVariables,
        );
        self::assertSame('testValue', $resetVariables['testVar']);
        self::assertTrue($resetVariables[AzineTemplateProvider::SEND_IMMEDIATELY_FLAG]);

        $registrationVariables = $provider->addTemplateVariablesFor(
            AzineTemplateProvider::FOS_USER_REGISTRATION_TEMPLATE,
            $contentVariables,
        );
        self::assertSame('testValue', $registrationVariables['testVar']);
        self::assertTrue($registrationVariables[AzineTemplateProvider::SEND_IMMEDIATELY_FLAG]);

        $contentVariables[AzineTemplateProvider::CONTENT_ITEMS] = [[
            AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => ['otherTestVar' => 'otherTestValue'],
        ]];
        $filledVariables = $provider->addTemplateVariablesFor(
            AzineTemplateProvider::BASE_TEMPLATE,
            $contentVariables,
        );

        self::assertSame('testValue', $filledVariables['testVar']);
        self::assertSame(
            'otherTestValue',
            $filledVariables[AzineTemplateProvider::CONTENT_ITEMS][0]
                [AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE]['otherTestVar'],
        );
        self::assertFileExists($filledVariables['logo_png']);
    }

    public function testAddsLocaleSpecificSnippetsRecursively(): void
    {
        $provider = $this->createProvider();
        $contentVariables = [
            'testVar' => 'testValue',
            AzineTemplateProvider::CONTENT_ITEMS => [[
                AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => ['otherTestVar' => 'otherTestValue'],
            ]],
        ];
        $contentVariables = $provider->addTemplateVariablesFor(
            AzineTemplateProvider::BASE_TEMPLATE,
            $contentVariables,
        );

        $english = $provider->addTemplateSnippetsWithImagesFor(
            AzineTemplateProvider::BASE_TEMPLATE,
            $contentVariables,
            'en',
        );
        $german = $provider->addTemplateSnippetsWithImagesFor(
            AzineTemplateProvider::BASE_TEMPLATE,
            $contentVariables,
            'de',
        );

        self::assertSame('testValue', $english['testVar']);
        self::assertStringContainsString('en translation', $english['linkToTop']);
        self::assertStringContainsString('de übersetzung', $german['linkToTop']);
        self::assertArrayHasKey(
            'linkToTop',
            $english[AzineTemplateProvider::CONTENT_ITEMS][0]
                [AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE],
        );
    }

    public function testSnippetGenerationRejectsMissingBaseVariables(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('required images');

        $this->createProvider()->addTemplateSnippetsWithImagesFor(
            AzineTemplateProvider::BASE_TEMPLATE,
            ['testVar' => 'testValue'],
            'en',
        );
    }

    public function testSnippetGenerationRequiresLocale(): void
    {
        $provider = $this->createProvider();
        $variables = $provider->addTemplateVariablesFor(AzineTemplateProvider::BASE_TEMPLATE, []);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('know in which language');

        $provider->addTemplateSnippetsWithImagesFor(
            AzineTemplateProvider::BASE_TEMPLATE,
            $variables,
            null,
        );
    }

    public function testCampaignParametersPreserveExistingBehavior(): void
    {
        $provider = $this->createProvider();

        self::assertSame(
            'newsletter',
            $provider->getCampaignParamsFor(AzineTemplateProvider::NEWSLETTER_TEMPLATE)['utm_source'],
        );
        self::assertSame(
            'mailnotify',
            $provider->getCampaignParamsFor(AzineTemplateProvider::NOTIFICATIONS_TEMPLATE)['utm_source'],
        );
        self::assertSame(
            'message',
            $provider->getCampaignParamsFor(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE)['utm_content'],
        );
        self::assertSame(
            [],
            $provider->getCampaignParamsFor(AzineTemplateProvider::FOS_USER_PWD_RESETTING_TEMPLATE),
        );
    }

    public function testAllowedImageFoldersAndWebPaths(): void
    {
        $provider = $this->createProvider();
        $allowedImage = $provider->getTemplateImageDir().'logo.png';
        $folderKey = $provider->isFileAllowed($allowedImage);

        self::assertIsString($folderKey);
        self::assertSame($provider->getTemplateImageDir(), $provider->getFolderFrom($folderKey));
        self::assertFalse($provider->isFileAllowed(__FILE__));
        self::assertFalse($provider->getFolderFrom('unknown'));

        $relative = $provider->makeImagePathsWebRelative(['logo' => $allowedImage], 'en');
        self::assertStringStartsWith('/template/images/', $relative['logo']);
        self::assertStringContainsString('_locale=en', $relative['logo']);
    }

    public function testWebViewPolicyAndTokenStayStable(): void
    {
        $provider = $this->createProvider();

        self::assertSame(AzineTemplateProvider::EMAIL_WEB_VIEW_TOKEN, $provider->getWebViewTokenId());
        self::assertTrue($provider->saveWebViewFor(AzineTemplateProvider::NEWSLETTER_TEMPLATE));
        self::assertFalse($provider->saveWebViewFor(AzineTemplateProvider::NOTIFICATIONS_TEMPLATE));
        self::assertFalse($provider->saveWebViewFor(AzineTemplateProvider::FOS_USER_REGISTRATION_TEMPLATE));
    }

    public function testSymfonyMimeCustomHeaders(): void
    {
        $provider = $this->createProvider(true);
        $email = new Email();

        $provider->addCustomHeadersToEmail('testTemplate', $email, [
            AzineTemplateProvider::EMAIL_WEB_VIEW_TOKEN => 'testToken',
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME => 'testCampaignValue',
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE => 'testSourceValue',
        ]);

        $headers = $email->getHeaders();
        self::assertSame('testToken', $headers->get('x-azine-webview-token')?->getBodyAsString());
        self::assertSame('testCampaignValue', $headers->get('x-utm_campaign')?->getBodyAsString());
        self::assertSame('testSourceValue', $headers->get('x-utm_source')?->getBodyAsString());
    }

    private function createProvider(bool $symfonyMailerProvider = false): AzineTemplateProvider
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static function (
                string $id,
                array $parameters = [],
                ?string $domain = null,
                ?string $locale = null,
            ): string {
                return 'de' === $locale ? 'de übersetzung' : 'en translation';
            });

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router
            ->method('generate')
            ->willReturnCallback(static function (string $routeName, array $parameters = []): string {
                if ('azine_email_serve_template_image' === $routeName) {
                    return sprintf(
                        '/template/images/%s?_locale=%s',
                        $parameters['filename'],
                        $parameters['_locale'],
                    );
                }

                return '/some/relative/url';
            });

        $parameters = [
            AzineEmailExtension::TEMPLATE_IMAGE_DIR => realpath(__DIR__.'/../../Resources/htmlTemplateImages/'),
            AzineEmailExtension::ALLOWED_IMAGES_FOLDERS => [
                realpath(__DIR__.'/../../Resources/htmlTemplateImages/'),
            ],
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME => 'utm_campaign',
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM => 'utm_term',
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE => 'utm_source',
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM => 'utm_medium',
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT => 'utm_content',
        ];

        return $symfonyMailerProvider
            ? new SymfonyMailerTemplateProvider($router, $translator, $parameters)
            : new AzineTemplateProvider($router, $translator, $parameters);
    }
}
