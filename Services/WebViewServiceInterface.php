<?php
namespace Azine\EmailBundle\Services;

/**
 * Interface with methods required by the AzineEmailBundle to render the email-content in the web-view
 *
 * @author dominik
 */
interface WebViewServiceInterface
{
    /**
     * Get a list of templates that you would like to be able to preview and send test-mails for from the AzineEmailBundle:WebView:indexAction
     * @return array of associative arrays of strings
     */
    public function getTemplatesForWebPreView();

    /**
     * Get a list of email-addresses that you would like to be able to send test-mails with dummy-data from the AzineEmailBundle:WebView:indexAction
     * @return array of associative arrays of strings
     */
    public function getTestMailAccounts();

    /**
     * Get the dummy-content for the email to be rendered in the webPreView or sent to the test-account.
     * @param  string $template : the template id in standard-notation, without the ending ( .txt.twig) => "AcmeFooBundle:bar:default"
     * @param  string $locale   : the locale for the templateVars
     * @return array  with all the content-variables needed to render the email. (the template variables from the TemplateProvider will be added later).
     */
    public function getDummyVarsFor($template, $locale);

}
