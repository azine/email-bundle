<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\Services\SpamCheckService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AllowMockObjectsWithoutExpectations]
class SpamCheckServiceTest extends TestCase
{
    public function testReturnsStructuredSuccessfulReport(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://spamcheck.postmarkapp.com/filter', $url);
            self::assertStringContainsString('raw message', (string) $options['body']);
            self::assertStringContainsString('long', (string) $options['body']);

            return new MockResponse(json_encode([
                'success' => true,
                'score' => 1.3,
                'report' => 'Looks good',
                'rules' => [],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $report = (new SpamCheckService($client))->checkRawMessage('raw message');

        self::assertTrue($report['success']);
        self::assertSame(200, $report['curlHttpCode']);
        self::assertSame(1.3, $report['score']);
        self::assertSame('Looks good', $report['report']);
        self::assertSame('-', $report['message']);
    }

    public function testInvalidJsonProducesUsefulFailure(): void
    {
        $client = new MockHttpClient(new MockResponse('not-json', ['http_code' => 502]));

        $report = (new SpamCheckService($client))->checkRawMessage('raw message');

        self::assertFalse($report['success']);
        self::assertSame(502, $report['curlHttpCode']);
        self::assertSame('The spam-check service returned an invalid JSON response.', $report['message']);
    }

    public function testTransportFailureRetainsLegacyResultKeys(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->method('request')
            ->willThrowException(new TransportException('Connection failed'));

        $report = (new SpamCheckService($client))->checkRawMessage('raw message');

        self::assertFalse($report['success']);
        self::assertSame('-', $report['curlHttpCode']);
        self::assertSame('Connection failed', $report['curlError']);
        self::assertSame('The spam-check service could not be reached.', $report['message']);
    }

    public function testRejectsUnknownReportType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SpamCheckService(new MockHttpClient()))->checkRawMessage('raw message', 'unknown');
    }
}
