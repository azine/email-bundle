<?php

namespace Azine\EmailBundle\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigTemplateCompatibilityTest extends TestCase
{
    public function testAllBundledTemplatesUseTwig3Syntax(): void
    {
        $twig = new Environment(new ArrayLoader());

        foreach (array('addCampaignParamsForTemplate', 'printVars', 'trans') as $filter) {
            $twig->addFilter(new TwigFilter($filter, static fn (...$arguments) => $arguments[0] ?? ''));
        }

        foreach (array('form_end', 'form_row', 'form_start', 'is_granted', 'knp_pagination_render', 'path', 'trans', 'url') as $function) {
            $twig->addFunction(new TwigFunction($function, static fn (...$arguments) => $arguments[0] ?? ''));
        }

        $templates = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.'/../Resources/views', FilesystemIterator::SKIP_DOTS)
        );
        $parsedTemplates = 0;

        /** @var SplFileInfo $template */
        foreach ($templates as $template) {
            if ('twig' !== $template->getExtension()) {
                continue;
            }

            $source = file_get_contents($template->getPathname());
            self::assertIsString($source);
            self::assertStringNotContainsString('{% filter', $source, $template->getPathname());
            self::assertStringNotContainsString('{% spaceless', $source, $template->getPathname());
            self::assertStringNotContainsString('AzineEmailBundle::', $source, $template->getPathname());

            $twig->parse($twig->tokenize(new Source($source, $template->getPathname())));
            ++$parsedTemplates;
        }

        self::assertGreaterThan(0, $parsedTemplates);
    }
}
