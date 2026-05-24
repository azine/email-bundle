<?php

$file = __DIR__.'/../vendor/autoload.php';
if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies to run test suite. "php composer.phar install --dev"');
}

require_once $file;

if (!class_exists('\PHPUnit_Framework_TestCase') && class_exists('\PHPUnit\Framework\TestCase')) {
    class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');
} elseif (!class_exists('\PHPUnit\Framework\TestCase') && class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
}

if (!class_exists('Symfony\\Bundle\\TwigBundle\\TwigEngine')) {
    class_alias('Azine\\EmailBundle\\Tests\\Compat\\TwigEngineCompat', 'Symfony\\Bundle\\TwigBundle\\TwigEngine');
}

if (!class_exists('Doctrine\\Common\\Persistence\\ManagerRegistry') && class_exists('Doctrine\\Persistence\\ManagerRegistry')) {
    class_alias('Doctrine\\Persistence\\ManagerRegistry', 'Doctrine\\Common\\Persistence\\ManagerRegistry');
}
