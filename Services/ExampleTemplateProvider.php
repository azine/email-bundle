<?php
namespace Azine\EmailBundle\Services;

/**
 * This Service provides the templates and template-variables to be used for emails
 * This class is only an example. Implement your own!
 * @codeCoverageIgnore
 * @author Dominik Businger
 */
class ExampleTemplateProvider extends AzineTemplateProvider implements TemplateProviderInterface
{
    // design your own twig-templates for your custom emails
    // and list them here as constants, to avoid typos.
    const VIP_INFO_MAIL_TEMPLATE		= 'AcmeExampleEmailBundle:VIP:infoMail';
    const SOME_OTHER_MAIL_TEMPLATE		= 'AcmeExampleEmailBundle:Foo:marMail';
    // and also design your own base-template that should be used for newsletter/notification-emails
    // and define the template IDs for the newsletter- and notification-emails in your config.yml

    /**
     * @see Azine\EmailBundle\Services\AzineTemplateProvider::getParamArrayFor()
     * @param string $template
     * @return array
     */
    protected function getParamArrayFor($template)
    {
        // get the style-params from the parent (if you like)
        $newVars = parent::getParamArrayFor($template);

        // If you configured two SwiftMailers, one with spooling and one without, then
        // all templates that have set the "SEND_IMMEDIATELY_FLAG = true" will be sent with the
        // mailer that does not use spooling => faster email delivery => e.g. for the Reset-Password-Email.
        if($template == self::VIP_INFO_MAIL_TEMPLATE){
            $newVars[self::SEND_IMMEDIATELY_FLAG] = true;
        }

        // add template specific stuff
        if ($template == self::NOTIFICATIONS_TEMPLATE) {
            $newVars['%someUrl%'] = "http://example.com"; 				//$this->router->generate("your_route", $routeParamArray, UrlGeneratorInterface::ABSOLUTE_URL);
            $newVars['%someOtherUrl%'] = "http://example.com/other";	//$this->router->generate("your_route", $routeParamArray, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        // override some generic stuff needed for all templates
        $newVars["h2Style"]	= "style='padding:0; margin:0; font:bold 24px Arial; color:red; text-decoration:none;'";

        // add an image that should be embedded into the html-email.
        $newVars['someRandomImage'] = $this->getTemplateImageDir()."someRandomImage.png";
        // after the image has been added here, it will be base64-encoded so it can be embedded into a html-snippet
        // see self::getSnippetArrayFor()

        return $newVars;
    }

    /**
     * @see Azine\EmailBundle\Services\AzineTemplateProvider::getSnippetArrayFor()
     * @param string $template
     * @param array $vars
     * @param string $emailLocale
     * @return array
     * @throws \Exception
     */
    protected function getSnippetArrayFor($template, array $vars, $emailLocale)
    {
        // add a code snippet to reference the random image you added in the getParamArrayFor() method.
        // in the mean time it has been base64-encoded and attached as mime-part to your email.
        $vars['sampleSnippetWithImage'] = "<img src='".$vars['logo_png']."'>";
        // with this html-snippet you can display the "someRandomImage.png" from your
        // template-folder like this in your twig-template:   .... {{ sampleSnippetWithImage }} ...

        return parent::getSnippetArrayFor($template, $vars, $emailLocale);
    }

    /**
     * @see Azine\EmailBundle\Services\AzineTemplateProvider::addCustomHeaders()
     * @param string $template
     * @param \Swift_Message $message
     * @param array $params
     * @return array
     */
    public function addCustomHeaders($template, \Swift_Message $message, array $params)
    {
        // see http://documentation.mailgun.com/user_manual.html#attaching-data-to-messages
        // for an idea what could be added here.
        //$headerSet = $message->getHeaders();
        //$headerSet->addTextHeader($name, $value);
    }

    /**
     * @see Azine\EmailBundle\Services\AzineTemplateProvider::getCampaignParamsFor()
     * @param $templateId
     * @param array $params
     * @return array
     */
    public function getCampaignParamsFor($templateId, array $params = null)
    {
        $campaignParams = array();
        //if ($templateId == "AcmeFooBundle:bar:mail.template") {
        //      $campaignParams[$this->tracking_params_campaign_name] = "foo-bar-campaign";
        //      $campaignParams[$this->tracking_params_campaign_term] = "keyword";
        //} else {
            // get some other params
            $campaignParams = parent::getCampaignParamsFor($templateId, $params);
        //}
        return $campaignParams;
    }

    /**
     * Override this function to define which emails you want to make the web-view available and for which not.
     * @see Azine\EmailBundle\Services\AzineTemplateProvider::getTemplatesToStoreForWebView()
     * @return array
     */
    public function getTemplatesToStoreForWebView()
    {
        $include = parent::getTemplatesToStoreForWebView();
        $include[] = self::VIP_INFO_MAIL_TEMPLATE;

        return $include;
    }

}
