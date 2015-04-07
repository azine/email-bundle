<?php
namespace Azine\EmailBundle\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * You must override this service for your needs. Also see ExampleAzineWebViewSerive for some examples.
 *
 * @author dominik
 */
class AzineWebViewService implements WebViewServiceInterface
{
    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.WebViewServiceInterface::getTemplatesForWebPreView()
     */
    protected $templates = array();
    protected $emails = array();

    public function getTemplatesForWebPreView()
    {
        $this->addTemplate("Reset Passwort Email", AzineTemplateProvider::FOS_USER_PWD_RESETTING_TEMPLATE);
        $this->addTemplate("Account activation", AzineTemplateProvider::FOS_USER_REGISTRATION_TEMPLATE);
        // override this method to add your own templates
        // $this->addTemplate("Some other mail",	ExampleTemplateProvider::SOME_OTHER_MAIL_TEMPLATE);
        // $this->addTemplate("VIP Infos",	ExampleTemplateProvider::VIP_INFO_MAIL_TEMPLATE);
        return $this->templates;
    }

    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.WebViewServiceInterface::getTestMailAccounts()
     */
    public function getTestMailAccounts()
    {
        // override this method to add your own emails
        // $this->addTestMailAccount('Testmail-account for MS Outlook',	'your.email.address@for.an.outlook.client.com');
        // $this->addTestMailAccount('Testmail-account for Gmail', 	'your.email.address@gmail');
        return $this->emails;
    }

    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.WebViewServiceInterface::getDummyVarsFor()
     */
    public function getDummyVarsFor($template, $locale, $variables = array())
    {
        $variables['subject'] = "some dummy subject";
        $variables['sendMailAccountName'] = "some name";
        $variables['sendMailAccountAddress'] = "no-reply@email.com";

        // override this method to provide dummy-variables
        // to view rendered templates for emails that you didn't send yet
        // or to send an email with dummy-variables to your test-account(s)
        //
        // do something like this:
        //
        // if ($template == ExampleTemplateProvider::VIP_INFO_MAIL_TEMPLATE) {
        // 	$vipVars = array();
        // 	$vipVars['vipInfos'] = $someService->getVipInfosFor($aUser);
        // 	$vipVars['userTitle'] = "You majesty";
        // 	$variables['contentItems'][] = array(ExampleTemplateProvider::VIP_INFO_MAIL_TEMPLATE, $vipVars);

        // } elseif ($template == ExampleTemplateProvider::SOME_OTHER_MAIL_TEMPLATE) {
        // 	$otherMailVars = array();
        // 	$otherMailVars['date'] = new \DateTime("long ago");
        // 	$variables['contentItems'][] = array(ExampleTemplateProvider::SOME_OTHER_MAIL_TEMPLATE, $otherMailVars);
        // }
        return $variables;
    }

    /**
     * @param UrlGeneratorInterface $router
     */
    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

//////////////////////////////////////////////////////////////////////////
    /* You probably don't need to change or override any of the stuff below */
//////////////////////////////////////////////////////////////////////////

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    /**
     * Add an email of a test account, you might want to send html-emails to, to verify the template before sending the emails to "real" recipients/users.
     * @param  array  $emails
     * @param  string $description
     * @param  string $emailAddress
     * @return array  the array with the added email-test-account
     */
    protected function addTestMailAccount($description, $emailAddress)
    {
        $this->emails[] = array('accountDescription' => $description, 'accountEmail' => $emailAddress);

        return $this->emails;
    }

    /**
     * Add the required variables to the $templates-array, so the line can be rendered in the template-index
     * @param  array       $templates
     * @param  string      $description
     * @param  string      $templateId
     * @param  array       $formats
     * @return array
     */
    protected function addTemplate($description, $templateId, $formats = array('txt', 'html'))
    {
        $route = $this->router->generate("azine_email_web_preview", array('template' => $templateId));

        $template = array(	'url' 			=> $route,
                            'description'	=> $description,
                            'formats' 		=> $formats,
                            'templateId'	=> $templateId,
        );

        $this->templates[] = $template;

        return $this->templates;
    }

}
