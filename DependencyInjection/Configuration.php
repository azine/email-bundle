<?php

namespace Azine\EmailBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('azine_geo_blocking');

        $rootNode
        	->children()
	        	->booleanNode	("enabled")						->defaultTrue()->info("true|false : turn the whole bundle on/off")->end()
	        	->scalarNode	('access_denied_view')			->defaultValue('AzineEmailBundle::accessDenied.html.twig')->info("the view to be rendered as 'blocked' page")->end()
	        	->booleanNode	('block_anonymouse_users_only')	->defaultTrue()->info("block all users or only users that are not logged in yet")->end()
	        	->scalarNode	('login_route')					->defaultValue('fos_user_security_login')->info("route name to the login-form (only relevant if block_anonymouse_users_only is set to true)")->end()
	        	->scalarNode	('lookup_adapter')				->defaultValue('azine_geo_blocking.lookup.adapter')->info("id of the lookup-adapter you would like to use")->end()
	        	->booleanNode	('allow_private_ips')			->defaultTrue()->info("true | false : also applie the rules to private IPs e.g. 127.0.0.1 or 192.168.xxx.yyy etc.")->end()
	        	->arrayNode		('countries')->info("only whitelist or blacklist can contain values.")->addDefaultsIfNotSet()
		        	->children()
		        		->variableNode('whitelist')->defaultValue(array())->info("e.g. 'CH','FR','DE' etc. => access is allowed to visitors from these countries")->end()
		        		->variableNode('blacklist')->defaultValue(array())->info("e.g. 'US','CN' etc. => access is denied to visitors from these countries")->end()
	        		->end()
	        	->end()// end countries
	        	->arrayNode		('routes')->info("only whitelist or blacklist can contain values.")->addDefaultsIfNotSet()
		        	->children()
		        		->variableNode('whitelist')->defaultValue(array('fos_user_security_login', 'fos_user_security_login_check', 'fos_user_security_logout'))->info("list of routes, that never should be blocked for access from unliked locations (e.g. the login-routes).")->cannotBeEmpty()->end()
		        		->variableNode('blacklist')->defaultValue(array())->info("list of routes, that always should be blocked for access from unliked locations.")->end()
	        		->end()
	        	->end()// end routes
	        ->end();

        return $treeBuilder;
    }
}
