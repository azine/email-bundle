<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

use Symfony\Component\Mime\Email;

/**
 * @deprecated Use TemplateTwigMailerInterface. The methods now expose Symfony Mime messages.
 */
interface TemplateTwigSwiftMailerInterface
{
    public function sendEmail(
        array &$failedRecipients,
        string $subject,
        ?string $from,
        ?string $fromName,
        string|array $to,
        ?string $toName,
        string|array|null $cc,
        ?string $ccName,
        string|array|null $bcc,
        ?string $bccName,
        string|array|null $replyTo,
        ?string $replyToName,
        array $params,
        string $template,
        array $attachments = [],
        ?string $emailLocale = null,
        ?Email &$message = null,
    ): int;

    public function sendSingleEmail(
        string|array $to,
        ?string $toName,
        string $subject,
        array $params,
        string $template,
        ?string $emailLocale,
        ?string $from = null,
        ?string $fromName = null,
        ?Email &$message = null,
    ): bool;
}
