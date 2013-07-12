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
	const RECIPIENT_PROVIDER =			"recipient_provider";
	const RECIPIENT_CLASS =				"recipient_class";
	const RECIPIENT_NEWSLETTER_FIELD=	"recipient_newsletter_field";
	const NO_REPLY =					"no_reply";
	const NO_REPLY_EMAIL_ADDRESS =		"email";
	const NO_REPLY_EMAIL_NAME =			"name";
	const TEMPLATE_IMAGE_DIR =			"image_dir";
	const TEMPLATE_PROVIDER = 			"template_provider";
	const TEMPLATE_TWIG_SWIFT_MAILER =	"template_twig_swift_mailer";
	const NOTIFIER_SERVICE =			"notifier_service";
	const NEWSLETTER =					"newsletter";
	const NEWSLETTER_INTERVAL = 		"interval";
	const NEWSLETTER_SEND_TIME =		"send_time";
	const WEB_VIEW_SERVICE =			"web_view_service";
	const WEB_VIEW_RETENTION =			"web_view_retention";
	const PREFIX =						"azine_email_";



	/**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $prefix = self::PREFIX;
        $container->setAlias	($prefix.self::RECIPIENT_PROVIDER,			$config[self::RECIPIENT_PROVIDER]);
        $container->setParameter($prefix.self::RECIPIENT_CLASS,				$config[self::RECIPIENT_CLASS]);
        $container->setParameter($prefix.self::RECIPIENT_NEWSLETTER_FIELD,	$config[self::RECIPIENT_NEWSLETTER_FIELD]);
        $container->setAlias	($prefix.self::TEMPLATE_PROVIDER,			$config[self::TEMPLATE_PROVIDER]);
        $container->setAlias	($prefix.self::TEMPLATE_TWIG_SWIFT_MAILER,	$config[self::TEMPLATE_TWIG_SWIFT_MAILER]);
        $container->setParameter($prefix.'no_reply',	array(	'email' => 	$config[self::NO_REPLY][self::NO_REPLY_EMAIL_ADDRESS],
        														'name' => 	$config[self::NO_REPLY][self::NO_REPLY_EMAIL_NAME]));
        $container->setParameter($prefix.self::TEMPLATE_IMAGE_DIR,			$config[self::TEMPLATE_IMAGE_DIR]);
        $container->setAlias	($prefix.self::NOTIFIER_SERVICE,			$config[self::NOTIFIER_SERVICE]);

        $container->setParameter($prefix.self::NEWSLETTER."_".self::NEWSLETTER_INTERVAL,		$config[self::NEWSLETTER][self::NEWSLETTER_INTERVAL]);
        $container->setParameter($prefix.self::NEWSLETTER."_".self::NEWSLETTER_SEND_TIME,		$config[self::NEWSLETTER][self::NEWSLETTER_SEND_TIME]);

        $container->setAlias	($prefix.self::WEB_VIEW_SERVICE,			$config[self::WEB_VIEW_SERVICE]);
        $container->setParameter($prefix.self::WEB_VIEW_RETENTION,			$config[self::WEB_VIEW_RETENTION]);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

    }
}
