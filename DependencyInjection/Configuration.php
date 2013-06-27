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
	        	->scalarNode	(AzineEmailExtension::NOTIFIABLE_PROVIDER)			->defaultValue('azine_email.notifiable.provider')		->info("the service-id of your implementation of the the NotifiableInterface")->end()
	        	->scalarNode	(AzineEmailExtension::TEMPLATE_TWIG_SWIFT_MAILER)	->defaultValue('azine_email.template_twig_swift_mailer')->info("the service-id of the mailer service to be used")->end()
	        	->arrayNode(AzineEmailExtension::NO_REPLY)
	        		->addDefaultsIfNotSet()
                    ->children()
	        		    ->scalarNode	(AzineEmailExtension::NO_REPLY_EMAIL_ADDRESS)	->defaultValue("no-reply@example.com")->cannotBeEmpty()		->info("the no-reply email-address")->end()
	        			->scalarNode	(AzineEmailExtension::NO_REPLY_EMAIL_NAME)		->defaultValue('azine notification daemon')->cannotBeEmpty()->info("the name to appear with the 'no-reply'-address.")->end()
	        			->end()
	        		->end()
	        	->scalarNode	(AzineEmailExtension::TEMPLATE_IMAGE_DIR)			->defaultValue('../Resources/htmlTemplateImages/')		->info("image folder, relative to the directory of your TemplateProviderService.")->end()
	        ->end();

        return $treeBuilder;
    }
}
