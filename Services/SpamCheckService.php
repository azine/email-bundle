<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpamCheckService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpoint = 'https://spamcheck.postmarkapp.com/filter',
    ) {
    }

    public function checkMessage(RawMessage $message, string $report = 'long'): array
    {
        return $this->checkRawMessage($message->toString(), $report);
    }

    /**
     * @return array{success: bool, message: string, curlHttpCode: int|string, curlError?: string, score?: float|int, report?: string, rules?: array}
     */
    public function checkRawMessage(string $messageSource, string $report = 'long'): array
    {
        if (!in_array($report, ['short', 'long'], true)) {
            throw new \InvalidArgumentException('The spam report type must be either "short" or "long".');
        }

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'email' => $messageSource,
                    'options' => $report,
                ],
                'timeout' => 5.0,
            ]);

            $statusCode = $response->getStatusCode();
            $decoded = json_decode($response->getContent(false), true);
            $result = is_array($decoded) ? $decoded : [];
            $result['curlHttpCode'] = $statusCode;
            $result['success'] = true === ($result['success'] ?? false);
            $result['message'] ??= '-';

            if ([] === $decoded) {
                $result['success'] = false;
                $result['message'] = 'The spam-check service returned an invalid JSON response.';
            } elseif (!$result['success'] && str_contains($messageSource, 'Content-Transfer-Encoding: base64')) {
                $result['message'] .= "\n\nRemoving base64-encoded MIME parts may help.";
            }

            return $result;
        } catch (TransportExceptionInterface $exception) {
            return [
                'success' => false,
                'curlHttpCode' => '-',
                'curlError' => $exception->getMessage(),
                'message' => 'The spam-check service could not be reached.',
            ];
        }
    }
}
