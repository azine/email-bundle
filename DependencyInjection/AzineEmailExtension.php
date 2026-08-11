<?php

declare(strict_types=1);

namespace Azine\EmailBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class AzineEmailExtension extends Extension
{
    public const RECIPIENT_PROVIDER = 'recipient_provider';
    public const RECIPIENT_CLASS = 'recipient_class';
    public const RECIPIENT_NEWSLETTER_FIELD = 'recipient_newsletter_field';
    public const NO_REPLY = 'no_reply';
    public const NO_REPLY_EMAIL_ADDRESS = 'email';
    public const NO_REPLY_EMAIL_NAME = 'name';
    public const TEMPLATE_IMAGE_DIR = 'image_dir';
    public const ALLOWED_IMAGES_FOLDERS = 'allowed_images_folders';
    public const TEMPLATE_PROVIDER = 'template_provider';
    public const TEMPLATE_TWIG_MAILER = 'template_twig_mailer';
    public const TEMPLATE_TWIG_SWIFT_MAILER = 'template_twig_swift_mailer';
    public const IMMEDIATE_MAILER_SERVICE = 'immediate_mailer_service';
    public const NOTIFIER_SERVICE = 'notifier_service';
    public const NEWSLETTER = 'newsletter';
    public const NEWSLETTER_INTERVAL = 'interval';
    public const NEWSLETTER_SEND_TIME = 'send_time';
    public const WEB_VIEW_SERVICE = 'web_view_service';
    public const WEB_VIEW_RETENTION = 'web_view_retention';
    public const TRACKING_PARAM_CAMPAIGN_NAME = 'tracking_params_campaign_name';
    public const TRACKING_PARAM_CAMPAIGN_TERM = 'tracking_params_campaign_term';
    public const TRACKING_PARAM_CAMPAIGN_CONTENT = 'tracking_params_campaign_content';
    public const TRACKING_PARAM_CAMPAIGN_MEDIUM = 'tracking_params_campaign_medium';
    public const TRACKING_PARAM_CAMPAIGN_SOURCE = 'tracking_params_campaign_source';
    public const EMAIL_TRACKING_BASE_URL = 'email_open_tracking_url';
    public const EMAIL_TRACKING_CODE_BUILDER = 'email_open_tracking_code_builder';
    public const DOMAINS_FOR_TRACKING = 'domains_for_tracking';
    public const PREFIX = 'azine_email_';
    public const TEMPLATES = 'templates';
    public const NEWSLETTER_TEMPLATE = 'newsletter';
    public const NOTIFICATIONS_TEMPLATE = 'notifications';
    public const CONTENT_ITEM_TEMPLATE = 'content_item';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $prefix = self::PREFIX;

        $container->setAlias($prefix.self::RECIPIENT_PROVIDER, $config[self::RECIPIENT_PROVIDER]);
        $container->setParameter($prefix.self::RECIPIENT_CLASS, $config[self::RECIPIENT_CLASS]);
        $container->setParameter($prefix.self::RECIPIENT_NEWSLETTER_FIELD, $config[self::RECIPIENT_NEWSLETTER_FIELD]);
        $container->setAlias($prefix.self::TEMPLATE_PROVIDER, $config[self::TEMPLATE_PROVIDER]);

        $mailerService = $config[self::TEMPLATE_TWIG_SWIFT_MAILER] ?: $config[self::TEMPLATE_TWIG_MAILER];
        $container->setAlias($prefix.self::TEMPLATE_TWIG_MAILER, $mailerService);
        $container->setAlias($prefix.self::TEMPLATE_TWIG_SWIFT_MAILER, $prefix.self::TEMPLATE_TWIG_MAILER);
        $container->setAlias($prefix.self::IMMEDIATE_MAILER_SERVICE, $config[self::IMMEDIATE_MAILER_SERVICE]);

        $container->setParameter($prefix.self::NO_REPLY, [
            self::NO_REPLY_EMAIL_ADDRESS => $config[self::NO_REPLY][self::NO_REPLY_EMAIL_ADDRESS],
            self::NO_REPLY_EMAIL_NAME => $config[self::NO_REPLY][self::NO_REPLY_EMAIL_NAME],
        ]);

        $container->setParameter(
            $prefix.self::TEMPLATE_IMAGE_DIR,
            realpath($config[self::TEMPLATE_IMAGE_DIR]) ?: $config[self::TEMPLATE_IMAGE_DIR],
        );

        $allowedFolders = [];
        foreach ($config[self::ALLOWED_IMAGES_FOLDERS] as $folder) {
            $allowedFolders[] = realpath($folder) ?: $folder;
        }
        $container->setParameter($prefix.self::ALLOWED_IMAGES_FOLDERS, array_values(array_unique($allowedFolders)));
        $container->setAlias($prefix.self::NOTIFIER_SERVICE, $config[self::NOTIFIER_SERVICE]);

        $container->setParameter($prefix.self::NEWSLETTER.'_'.self::NEWSLETTER_INTERVAL, $config[self::NEWSLETTER][self::NEWSLETTER_INTERVAL]);
        $container->setParameter($prefix.self::NEWSLETTER.'_'.self::NEWSLETTER_SEND_TIME, $config[self::NEWSLETTER][self::NEWSLETTER_SEND_TIME]);
        $container->setParameter($prefix.self::TEMPLATES.'_'.self::NEWSLETTER_TEMPLATE, $config[self::TEMPLATES][self::NEWSLETTER_TEMPLATE]);
        $container->setParameter($prefix.self::TEMPLATES.'_'.self::NOTIFICATIONS_TEMPLATE, $config[self::TEMPLATES][self::NOTIFICATIONS_TEMPLATE]);
        $container->setParameter($prefix.self::TEMPLATES.'_'.self::CONTENT_ITEM_TEMPLATE, $config[self::TEMPLATES][self::CONTENT_ITEM_TEMPLATE]);

        foreach ([
            self::TRACKING_PARAM_CAMPAIGN_CONTENT,
            self::TRACKING_PARAM_CAMPAIGN_MEDIUM,
            self::TRACKING_PARAM_CAMPAIGN_NAME,
            self::TRACKING_PARAM_CAMPAIGN_SOURCE,
            self::TRACKING_PARAM_CAMPAIGN_TERM,
            self::EMAIL_TRACKING_BASE_URL,
            self::DOMAINS_FOR_TRACKING,
        ] as $key) {
            $container->setParameter($prefix.$key, $config[$key]);
        }

        $container->setAlias($prefix.self::EMAIL_TRACKING_CODE_BUILDER, $config[self::EMAIL_TRACKING_CODE_BUILDER]);
        $container->setAlias($prefix.self::WEB_VIEW_SERVICE, $config[self::WEB_VIEW_SERVICE]);
        $container->setParameter($prefix.self::WEB_VIEW_RETENTION, $config[self::WEB_VIEW_RETENTION]);

        if (!$container->hasParameter('azine_email_update_confirmation.template')) {
            $container->setParameter(
                'azine_email_update_confirmation.template',
                '@AzineEmailUpdateConfirmation/Email/email_update_confirmation.txt.twig',
            );
        }

        (new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config')))->load('services.yml');
    }
}
