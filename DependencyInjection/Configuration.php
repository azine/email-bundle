<?php

namespace Azine\EmailBundle\DependencyInjection;

use Azine\EmailBundle\Services\AzineTemplateProvider;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Define the possible configuration settings for the config.yml/.xml
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('azine_email');

        $rootNode
            ->children()
                ->scalarNode	(AzineEmailExtension::RECIPIENT_CLASS)->isRequired()	->defaultValue("Acme\\SomeBundle\\Entity\\User")				->info("the class of your implementation of the RecipientInterface")->end()
                ->scalarNode	(AzineEmailExtension::RECIPIENT_NEWSLETTER_FIELD)		->defaultValue("newsletter")									->info("the fieldname of the boolean field on the recipient class indicating, that a newsletter should be sent or not")->end()
                ->scalarNode	(AzineEmailExtension::NOTIFIER_SERVICE)					->defaultValue('azine_email.example.notifier_service')			->info("the service-id of your implementation of the nofitier service to be used")->end()
                ->scalarNode	(AzineEmailExtension::TEMPLATE_PROVIDER)->isRequired()	->defaultValue('azine_email.example.template_provider')			->info("the service-id of your implementation of the template provider service to be used")->end()
                ->scalarNode	(AzineEmailExtension::RECIPIENT_PROVIDER)				->defaultValue('azine_email.default.recipient_provider')		->info("the service-id of the implementation of the RecipientProviderInterface to be used")->end()
                ->scalarNode	(AzineEmailExtension::TEMPLATE_TWIG_SWIFT_MAILER)		->defaultValue('azine_email.default.template_twig_swift_mailer')->info("the service-id of the mailer service to be used")->end()
                ->arrayNode(AzineEmailExtension::NO_REPLY)->isRequired()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode	(AzineEmailExtension::NO_REPLY_EMAIL_ADDRESS)	->defaultValue("no-reply@example.com")->isRequired()		->info("the no-reply email-address")->isRequired()->end()
                        ->scalarNode	(AzineEmailExtension::NO_REPLY_EMAIL_NAME)		->defaultValue('notification daemon')->isRequired()			->info("the name to appear with the 'no-reply'-address.")->isRequired()->end()
                        ->end()
                    ->end()
                ->scalarNode	(AzineEmailExtension::TEMPLATE_IMAGE_DIR)				->defaultValue('%kernel.root_dir%/../vendor/azine/email-bundle/Azine/EmailBundle/Resources/htmlTemplateImages/')		->info("absolute path to the image-folder containing the images used in your templates.")->end()
                ->variableNode(AzineEmailExtension::ALLOWED_IMAGES_FOLDERS)				->defaultValue(array())										->info("list of folders from which images are allowed to be embeded into emails")->end()
                ->arrayNode(AzineEmailExtension::NEWSLETTER)->info("newsletter configuration")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode(AzineEmailExtension::NEWSLETTER_INTERVAL)		->defaultValue('14')		->info("number of days between newsletters")->end()
                        ->scalarNode(AzineEmailExtension::NEWSLETTER_SEND_TIME)			->defaultValue('10:00')		->info("time of the day, when newsletters should be sent, 24h-format => e.g. 23:59")->end()
                    ->end()
                ->end()

                ->arrayNode(AzineEmailExtension::TEMPLATES)->info("templates configuration")
                    ->addDefaultsIfNotSet()
                    ->children()
                           ->scalarNode(AzineEmailExtension::NEWSLETTER_TEMPLATE)		->defaultValue(AzineTemplateProvider::NEWSLETTER_TEMPLATE)				->info("wrapper template id (without ending) for the newsletter")->end()
                        ->scalarNode(AzineEmailExtension::NOTIFICATIONS_TEMPLATE)	    ->defaultValue(AzineTemplateProvider::NOTIFICATIONS_TEMPLATE)			->info("wrapper template id (without ending) for notifications")->end()
                        ->scalarNode(AzineEmailExtension::CONTENT_ITEM_TEMPLATE)	    ->defaultValue(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE)	->info("template id (without ending) for notification content items")->end()
                    ->end()
                ->end()

                ->scalarNode	(AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME)		->defaultValue('utm_campaign')	->info("See https://ga-dev-tools.appspot.com/campaign-url-builder/ for more infos")->end()
                ->scalarNode	(AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM)		->defaultValue('utm_term')		->info("See https://ga-dev-tools.appspot.com/campaign-url-builder/ for more infos")->end()
                ->scalarNode	(AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT)	->defaultValue('utm_content')	->info("See https://ga-dev-tools.appspot.com/campaign-url-builder/ for more infos")->end()
                ->scalarNode	(AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM)	->defaultValue('utm_medium')	->info("See https://ga-dev-tools.appspot.com/campaign-url-builder/ for more infos")->end()
                ->scalarNode	(AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE)	->defaultValue('utm_source')	->info("See https://ga-dev-tools.appspot.com/campaign-url-builder/ for more infos")->end()
                ->scalarNode	(AzineEmailExtension::EMAIL_TRACKING_BASE_URL)	        ->defaultValue(null)	        ->info("See the README.md file for more information")->end()
                ->scalarNode	(AzineEmailExtension::EMAIL_TRACKING_CODE_BUILDER)	    ->defaultValue('azine.email.open.tracking.code.builder.ga.or.piwik')->info("Defaults to the AzineEmailOpenTrackingCodeBuilder. See the README.md file for more information")->end()
                ->arrayNode 	(AzineEmailExtension::DOMAINS_FOR_TRACKING)->info("Defaults to 'all domains' => empty array.")
                    ->prototype('scalar')->end()
                ->end()

                ->scalarNode	(AzineEmailExtension::WEB_VIEW_RETENTION)				->defaultValue('90')			->info("number of days that emails should be available in web-view")->end()
                ->scalarNode	(AzineEmailExtension::WEB_VIEW_SERVICE)					->defaultValue('azine_email.example.web.view.service')			->info("the service-id of your implementation of the web view service to be used")->end()
            ;

        return $treeBuilder;
    }
}
