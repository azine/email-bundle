<?php

namespace Azine\EmailBundle\DependencyInjection;

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
	        	->scalarNode	(AzineEmailExtension::RECIPIENT_NEWSLETTER_FIELD)		->defaultValue("news_letter")									->info("the fieldname of the boolean field on the recipient class indicating, that a newsletter should be sent or not")->end()
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
	        	->scalarNode	(AzineEmailExtension::TEMPLATE_IMAGE_DIR)			->defaultValue('%kernel.root_dir%/../vendor/azine/email-bundle/Azine/EmailBundle/Resources/htmlTemplateImages/')		->info("absolute path to the image-folder containing the images used in your templates.")->end()
	        ->end();

        return $treeBuilder;
    }
}
