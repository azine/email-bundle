<?php

namespace Azine\EmailBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * Configure the bundle with the values from the config.yml/.xml
 */
class AzineEmailExtension extends Extension
{
	const NOTIFIABLE_PROVIDER =			"notifiable_provider";
	const NO_REPLY =					"no_reply";
	const NO_REPLY_EMAIL_ADDRESS =		"email";
	const NO_REPLY_EMAIL_NAME =			"name";
	const TEMPLATE_IMAGE_DIR =			"template_image_dir";
	const TEMPLATE_TWIG_SWIFT_MAILER =	"template_twig_swift_mailer";


	/**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $prefix = 'azine_email_';
        $container->setParameter($prefix.self::NOTIFIABLE_PROVIDER,			$config[self::NOTIFIABLE_PROVIDER]);
        $container->setParameter($prefix.self::TEMPLATE_TWIG_SWIFT_MAILER,	$config[self::TEMPLATE_TWIG_SWIFT_MAILER]);
        $container->setParameter($prefix.'no_reply',	array(	'email' => 	$config[self::NO_REPLY][self::NO_REPLY_EMAIL_ADDRESS],
        														'name' => 	$config[self::NO_REPLY][self::NO_REPLY_EMAIL_NAME]));
        $container->setParameter($prefix.self::TEMPLATE_IMAGE_DIR,			$config[self::TEMPLATE_IMAGE_DIR]);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

    }
}
