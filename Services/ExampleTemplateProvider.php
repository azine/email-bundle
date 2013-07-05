<?php
namespace Azine\EmailBundle\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This Service provides the templates and template-variables to be used for emails
 * @author Dominik Businger
 */
class EmailTemplateProvider extends AzineTemplateProvider implements TemplateProviderInterface {

	// design your own templates for newsletter/notifications.
	const NEWSLETTER_TEMPLATE 				= 'AzineEmailBundle::baseEmailLayout';
	const NOTIFICATIONS_TEMPLATE			= 'AzineEmailBundle::baseEmailLayout';
	const CONTENT_ITEM_MESSAGE_TEMPLATE		= 'AzineEmailBundle:contentItem:message';

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.AzineTemplateProvider::fillTemplatesArrayFor()
	 */
	 protected function fillTemplatesArrayFor($type){
		if ($type == TemplateProviderInterface::NEWSLETTER_TYPE){
			$this->templateArray[$type] = self::NEWSLETTER_TEMPLATE;

		} else if($type == TemplateProviderInterface::NOTIFICATION_TYPE) {
				$this->templateArray[$type] = self::NOTIFICATIONS_TEMPLATE;
		} else {
			parent::fillTemplatesArrayFor($type);
		}
	 }

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.AzineTemplateProvider::fillParamArrayFor()
	 */
	protected function fillParamArrayFor($template){
		// don't add anything for message-templates, we don't need it
		if($template == self::CONTENT_ITEM_MESSAGE_TEMPLATE){
			$this->paramArrays[$template] = $newVars;
			return;
		}

		// get the style-params from the parent (if you like)
		$newVars = parent::fillParamArrayFor($template);

		// add template specific stuff
		if($template == self::NOTIFICATIONS_TEMPLATE){
			$newVars['%someUrl%'] = "http://example.com"; 				//$this->router->generate("your_route", $routeParamArray, UrlGeneratorInterface::ABSOLUTE_URL);
			$newVars['%someOtherUrl%'] = "http://example.com/other";	//$this->router->generate("your_route", $routeParamArray, UrlGeneratorInterface::ABSOLUTE_URL);
			$newVars['subject'] = $this->getTranslator()->trans("Your notification subject");
		}
		if($template == self::NEWSLETTER_TEMPLATE){
			$newVars['subject'] = $this->getTranslator()->trans("Your newsletter subject");
		}

		// override some generic stuff needed for all templates
		$newVars["h2Style"]	= "style='padding:0; margin:0; font:bold 24px Arial; color:red; text-decoration:none;'";

		$this->paramArrays[$template] = $newVars;
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.TemplateProviderInterface::getTemplateBlocksFor()
	 */
	public function addTemplateSnippetsWithEmbededImagesFor($template, array $vars, $emailLocale){
		// all templates except the CONTENT_ITEM_MESSAGE_TEMPLATE call the parent
		// function when adding styles => call the parent function here too for those templates.
		if($template != self::CONTENT_ITEM_MESSAGE_TEMPLATE){
			parent::addTemplateSnippetsWithEmbededImagesFor($template, $vars, $emailLocale);
		}

		return $vars;
	}

}
