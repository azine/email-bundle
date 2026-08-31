<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

interface WebViewServiceInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTemplatesForWebPreView();

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTestMailAccounts();

    /**
     * @return array<string, mixed>
     */
    public function getDummyVarsFor($template, $locale, $variables = []);
}
