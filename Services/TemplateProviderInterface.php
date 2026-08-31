<?php

namespace Azine\EmailBundle\Services;

/**
 * Provides template variables, snippets, tracking parameters and web-view metadata.
 *
 * Method signatures intentionally remain source-compatible for application-specific
 * providers that extend AzineTemplateProvider.
 */
interface TemplateProviderInterface
{
    public function addTemplateVariablesFor($template, array $contentVariables);

    public function addTemplateSnippetsWithImagesFor($template, array $vars, $emailLocale, $forWebView = false);

    public function getTemplateImageDir();

    public function makeImagePathsWebRelative(array $emailVars, $locale);

    public function isFileAllowed($filePath);

    public function getFolderFrom($key);

    public function saveWebViewFor($template);

    public function getWebViewTokenId();

    public function getCampaignParamsFor($templateId, ?array $params = null);
}
