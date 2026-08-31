<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests;

use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LocaleAwareTranslatorStub implements TranslatorInterface, LocaleAwareInterface
{
    public function __construct(private string $locale = 'en')
    {
    }

    public function trans(
        string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null,
    ): string {
        return strtr($id, array_map(
            static fn (mixed $value): string => (string) $value,
            $parameters,
        ));
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
