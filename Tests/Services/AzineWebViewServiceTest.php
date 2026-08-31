<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\Services\AzineWebViewService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AllowMockObjectsWithoutExpectations]
class AzineWebViewServiceTest extends TestCase
{
    public function testDefaultCollectionsAreArrays(): void
    {
        $service = new AzineWebViewService($this->createMock(UrlGeneratorInterface::class));

        self::assertIsArray($service->getTemplatesForWebPreView());
        self::assertIsArray($service->getTestMailAccounts());
        self::assertIsArray($service->getDummyVarsFor('some template', 'de'));
    }

    public function testAddTestMailAccount(): void
    {
        $service = new AzineWebViewService($this->createMock(UrlGeneratorInterface::class));
        $method = new \ReflectionMethod($service, 'addTestMailAccount');

        $accounts = $method->invoke($service, [], 'Some description', 'account@example.com');

        self::assertSame([
            [
                'accountDescription' => 'Some description',
                'accountEmail' => 'account@example.com',
            ],
        ], $accounts);
    }

    public function testAddTemplate(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router
            ->expects(self::once())
            ->method('generate')
            ->with('azine_email_web_preview', ['template' => 'someId'])
            ->willReturn('/some/url/to/the/preview');

        $service = new AzineWebViewService($router);
        $method = new \ReflectionMethod($service, 'addTemplate');
        $templates = $method->invoke(
            $service,
            [],
            'some new template',
            'someId',
            ['txt', 'html', 'xml'],
        );

        self::assertSame([
            [
                'url' => '/some/url/to/the/preview',
                'description' => 'some new template',
                'formats' => ['txt', 'html', 'xml'],
                'templateId' => 'someId',
            ],
        ], $templates);
    }
}
