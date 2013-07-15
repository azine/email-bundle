<?php
namespace Azine\EmailBundle\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This Service provides the templates and template-variables to be used for emails
 * @author Dominik Businger
 */
class ExampleTemplateProvider extends AzineTemplateProvider implements TemplateProviderInterface {

	// design your own templates for newsletter/notifications.
	const NEWSLETTER_TEMPLATE 				= 'AzineEmailBundle::baseEmailLayout';
	const NOTIFICATIONS_TEMPLATE			= 'AzineEmailBundle::baseEmailLayout';
	const CONTENT_ITEM_MESSAGE_TEMPLATE		= 'AzineEmailBundle:contentItem:message';
	const VIP_INFO_MAIL_TEMPLATE			= 'AcmeExampleEmailBundle:VIP:infoMail';
	const SOME_OTHER_MAIL_TEMPLATE			= 'AcmeExampleEmailBundle:Foo:marMail';

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
	 * @see Azine\EmailBundle\Services.AzineTemplateProvider::getParamArrayFor()
	 */
	protected function getParamArrayFor($template){
		// don't add anything for message-templates, we don't need it
		if($template == self::CONTENT_ITEM_MESSAGE_TEMPLATE){
			$this->paramArrays[$template] = $newVars;
			return;
		}

		// get the style-params from the parent (if you like)
		$newVars = parent::getParamArrayFor($template);

		// add template specific stuff
		if($template == self::NOTIFICATIONS_TEMPLATE){
			$newVars['%someUrl%'] = "http://example.com"; 				//$this->router->generate("your_route", $routeParamArray, UrlGeneratorInterface::ABSOLUTE_URL);
			$newVars['%someOtherUrl%'] = "http://example.com/other";	//$this->router->generate("your_route", $routeParamArray, UrlGeneratorInterface::ABSOLUTE_URL);
			$newVars['subject'] = $this->getTranslator()->trans("example.templateProvider.your.notification.subject", array());
		}
		if($template == self::NEWSLETTER_TEMPLATE){
			$newVars['subject'] = $this->getTranslator()->trans("example.templateProvider.your.newsletter.subject", array());
		}

		// override some generic stuff needed for all templates
		$newVars["h2Style"]	= "style='padding:0; margin:0; font:bold 24px Arial; color:red; text-decoration:none;'";

		// add an image that should be embeded into the html-email.
		$newVars['someRandomImage'] = $this->getTemplateImageDir()."someRandomImage.png";

		$this->paramArrays[$template] = $newVars;
	}

	/**
	 * Add any html-code snippets here that reference embeded images.
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.AzineTemplateProvider::getSnippetArrayFor()
	 */
	protected function getSnippetArrayFor($template, array $vars, $emailLocale){

		// add a code snippet to reference the random image you added in the getParamArrayFor() method.
		$vars['sampleSnippetWithImage'] = "<img src='".$vars['logo_png']."'>";

		// with this html-snippet you can display the "someRandomImage.png" from your
		// template-folder like this in your twig-template:   .... {{ sampleSnippetWithImage }} ...

		return parent::getSnippetArrayFor($template, $vars, $emailLocale);;
	}

	/**
	 * Override this function to define which emails you want to make the web-view available and for which not.
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.TemplateProviderInterface::saveWebViewFor()
	 */
	public function saveWebViewFor($template){

		// implement your own rules here
		if($template == self::VIP_INFO_MAIL_TEMPLATE){
			return false;
		}

		// the parent function returns true for all templates except the FOSUserBundle:Resetting:email* and FOSUserBundle:Registration:email* templates.
		return parent::saveWebViewFor($template);
	}


}
