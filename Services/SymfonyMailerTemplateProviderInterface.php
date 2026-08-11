<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

use Symfony\Component\Mime\Email;

interface SymfonyMailerTemplateProviderInterface
{
    public function addCustomHeadersToEmail(string $template, Email $message, array $params): void;
}
