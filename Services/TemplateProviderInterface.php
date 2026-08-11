<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

use Symfony\Component\Mime\Email;

interface TemplateProviderInterface
{
    public function addTemplateVariablesFor(string $template, array $contentVariables): array;

    public function addTemplateSnippetsWithImagesFor(
        string $template,
        array $vars,
        string $emailLocale,
        bool $forWebView = false,
    ): array;

    public function addCustomHeaders(string $template, Email $message, array $params): void;

    public function getTemplateImageDir(): string;

    public function makeImagePathsWebRelative(array $emailVars, string $locale): array;

    public function isFileAllowed(string $filePath): bool;

    public function getFolderFrom(string $key): string|false;

    public function saveWebViewFor(string $template): bool;

    public function getWebViewTokenId(): string;

    public function getCampaignParamsFor(string $templateId, ?array $params = null): array;
}
