<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Template;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

$aliases = [
    Environment::class => 'Twig_Environment',
    Template::class => 'Twig_Template',
    AbstractExtension::class => 'Twig_Extension',
    TwigFilter::class => 'Twig_SimpleFilter',
    TwigFunction::class => 'Twig_SimpleFunction',
    TwigTest::class => 'Twig_SimpleTest',
];

foreach ($aliases as $modernClass => $legacyClass) {
    if (!class_exists($legacyClass, false) && class_exists($modernClass)) {
        class_alias($modernClass, $legacyClass);
    }
}
