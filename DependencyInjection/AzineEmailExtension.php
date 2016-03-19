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
    const ALLOWED_IMAGES_FOLDERS = 		"allowed_images_folders";
    const TEMPLATE_PROVIDER = 			"template_provider";
    const TEMPLATE_TWIG_SWIFT_MAILER =	"template_twig_swift_mailer";
    const NOTIFIER_SERVICE =			"notifier_service";
    const NEWSLETTER =					"newsletter";
    const NEWSLETTER_INTERVAL = 		"interval";
    const NEWSLETTER_SEND_TIME =		"send_time";
    const WEB_VIEW_SERVICE =			"web_view_service";
    const WEB_VIEW_RETENTION =			"web_view_retention";

    const TRACKING_PARAM_CAMPAIGN_NAME    =	"tracking_params_campaign_name";
    const TRACKING_PARAM_CAMPAIGN_TERM    =	"tracking_params_campaign_term";
    const TRACKING_PARAM_CAMPAIGN_CONTENT =	"tracking_params_campaign_content";
    const TRACKING_PARAM_CAMPAIGN_MEDIUM  =	"tracking_params_campaign_medium";
    const TRACKING_PARAM_CAMPAIGN_SOURCE  =	"tracking_params_campaign_source";
    const EMAIL_TRACKING_BASE_URL         = "email_open_tracking_url";
    const EMAIL_TRACKING_CODE_BUILDER     = "email_open_tracking_code_builder";
    const DOMAINS_FOR_TRACKING            = "domains_for_tracking";
    const PREFIX =						"azine_email_";
    const TEMPLATES =					"templates";
    const NEWSLETTER_TEMPLATE =			"newsletter";
    const NOTIFICATIONS_TEMPLATE =		"notifications";
    const CONTENT_ITEM_TEMPLATE =		"content_item";

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
        $container->setParameter($prefix.self::TEMPLATE_IMAGE_DIR,			realpath($config[self::TEMPLATE_IMAGE_DIR]));
        $allowedFolders = array();
        foreach ($config[self::ALLOWED_IMAGES_FOLDERS] as $folder) {
            $allowedFolders[] = realpath($folder);
        }
        $container->setParameter($prefix.self::ALLOWED_IMAGES_FOLDERS,		$allowedFolders);
        $container->setAlias	($prefix.self::NOTIFIER_SERVICE,			$config[self::NOTIFIER_SERVICE]);

        $container->setParameter($prefix.self::NEWSLETTER."_".self::NEWSLETTER_INTERVAL,	$config[self::NEWSLETTER][self::NEWSLETTER_INTERVAL]);
        $container->setParameter($prefix.self::NEWSLETTER."_".self::NEWSLETTER_SEND_TIME,	$config[self::NEWSLETTER][self::NEWSLETTER_SEND_TIME]);

        $container->setParameter($prefix.self::TEMPLATES."_".self::NEWSLETTER_TEMPLATE,	    $config[self::TEMPLATES][self::NEWSLETTER_TEMPLATE]);
        $container->setParameter($prefix.self::TEMPLATES."_".self::NOTIFICATIONS_TEMPLATE,	$config[self::TEMPLATES][self::NOTIFICATIONS_TEMPLATE]);
        $container->setParameter($prefix.self::TEMPLATES."_".self::CONTENT_ITEM_TEMPLATE,	$config[self::TEMPLATES][self::CONTENT_ITEM_TEMPLATE]);

        $container->setParameter($prefix.self::TRACKING_PARAM_CAMPAIGN_CONTENT,	$config[self::TRACKING_PARAM_CAMPAIGN_CONTENT]);
        $container->setParameter($prefix.self::TRACKING_PARAM_CAMPAIGN_MEDIUM,	$config[self::TRACKING_PARAM_CAMPAIGN_MEDIUM]);
        $container->setParameter($prefix.self::TRACKING_PARAM_CAMPAIGN_NAME,	$config[self::TRACKING_PARAM_CAMPAIGN_NAME]);
        $container->setParameter($prefix.self::TRACKING_PARAM_CAMPAIGN_SOURCE,	$config[self::TRACKING_PARAM_CAMPAIGN_SOURCE]);
        $container->setParameter($prefix.self::TRACKING_PARAM_CAMPAIGN_TERM,	$config[self::TRACKING_PARAM_CAMPAIGN_TERM]);
        $container->setParameter($prefix.self::EMAIL_TRACKING_BASE_URL,	        $config[self::EMAIL_TRACKING_BASE_URL]);
        $container->setParameter($prefix.self::DOMAINS_FOR_TRACKING,	        $config[self::DOMAINS_FOR_TRACKING]);
        $container->setAlias    ($prefix.self::EMAIL_TRACKING_CODE_BUILDER,     $config[self::EMAIL_TRACKING_CODE_BUILDER]);

        $container->setAlias	($prefix.self::WEB_VIEW_SERVICE,			$config[self::WEB_VIEW_SERVICE]);
        $container->setParameter($prefix.self::WEB_VIEW_RETENTION,			$config[self::WEB_VIEW_RETENTION]);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

    }
}
