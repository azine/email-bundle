<?php
namespace Azine\EmailBundle\Services;

/**
 * This Service provides the templates and template-variables to be used for emails
 * This class is only an example. Implement your own!
 * @codeCoverageIgnoreStart
 * @author Dominik Businger
 */
class ExampleTemplateProvider extends AzineTemplateProvider implements TemplateProviderInterface {

	// design your own templates for newsletter/notifications.
	const VIP_INFO_MAIL_TEMPLATE		= 'AcmeExampleEmailBundle:VIP:infoMail';
	const SOME_OTHER_MAIL_TEMPLATE		= 'AcmeExampleEmailBundle:Foo:marMail';

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
	 * @param string $template
	 * @param \Swift_Message $message
	 * @param array $params
	 */
	public function addCustomHeaders($template, \Swift_Message $message, array $params) {
		// see http://documentation.mailgun.com/user_manual.html#attaching-data-to-messages
		// for an idea what could be added here.
		//$headerSet = $message->getHeaders();
		//$headerSet->addTextHeader($name, $value);
	}


	/**
	 * Override this function to define the campaign-parameters you like
	 * If you use GoogleAnalytics, look at this page https://support.google.com/analytics/answer/1033867
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.AzineTemplateProvider::getCampaignParamsFor()
	 */
	public function getCampaignParamsFor($templateId, array $params = null){
		$campaignParams = array();
		//if($templateId == "AcmeFooBundle:bar:mail.template"){
		//	$campaignParams[$this->campaignParamName] = "foo-bar-campaign";
		//	$campaignParams[$this->campaignKeyWordParamName] = "keyword";
		//} else {
			// get some other params
			$campaignParams = parent::getCampaignParamsFor($templateId, $params);
		//}
		return $campaignParams;
	}


	/**
	 * Override this function to define which emails you want to make the web-view available and for which not.
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.TemplateProviderInterface::saveWebViewFor()
	 */
	public function getTemplatesToStoreForWebView(){
		$include = parent::getTemplatesToStoreForWebView();
		$include[] = self::VIP_INFO_MAIL_TEMPLATE;
		return $include;
	}


}
