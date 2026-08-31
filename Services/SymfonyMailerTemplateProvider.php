<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Symfony\Component\Mime\Email;

class SymfonyMailerTemplateProvider extends AzineTemplateProvider implements SymfonyMailerTemplateProviderInterface
{
    /**
     * Keeps the historic method name so the mailer can work with both legacy and
     * Symfony-Mime-aware application providers during the migration.
     */
    public function addCustomHeaders($template, $message, array $params)
    {
        if ($message instanceof Email) {
            $this->addCustomHeadersToEmail((string) $template, $message, $params);

            return;
        }

        parent::addCustomHeaders($template, $message, $params);
    }

    public function addCustomHeadersToEmail(string $template, Email $message, array $params): void
    {
        $headers = $message->getHeaders();

        if (array_key_exists($this->getWebViewTokenId(), $params)) {
            $headers->addTextHeader(
                'x-azine-webview-token',
                (string) $params[$this->getWebViewTokenId()],
            );
        }

        if (array_key_exists(AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME, $params)) {
            $headers->addTextHeader(
                'x-utm_campaign',
                (string) $params[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME],
            );
        }

        if (array_key_exists(AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE, $params)) {
            $headers->addTextHeader(
                'x-utm_source',
                (string) $params[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE],
            );
        }
    }
}
