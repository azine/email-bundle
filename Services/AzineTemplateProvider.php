<?php

namespace Azine\EmailBundle\Services;

/*
 * This Service provides the templates and template-variables to be used for emails
 * @author Dominik Businger
 */
use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;

class AzineTemplateProvider implements TemplateProviderInterface
{
    const BASE_TEMPLATE = 'AzineEmailBundle::baseEmailLayout';
    const NEWSLETTER_TEMPLATE = 'AzineEmailBundle::newsletterEmailLayout';
    const NOTIFICATIONS_TEMPLATE = 'AzineEmailBundle::notificationsEmailLayout';
    const CONTENT_ITEM_MESSAGE_TEMPLATE = 'AzineEmailBundle:contentItem:message';
    const EMAIL_UPDATE_CONFIRMATION_TEMPLATE = '@AzineEmailUpdateConfirmation/Email/email_update_confirmation';
    const FOS_USER_PWD_RESETTING_TEMPLATE = '@FOSUser/Resetting/email';
    const FOS_USER_REGISTRATION_TEMPLATE = '@FOSUser/Registration/email';
    const FOS_USER_CONFIRM_EMAIL_UPDATE = '@FOSUser/Profile/email_update_confirmation';
    const SEND_IMMEDIATELY_FLAG = 'AzineEmailBundle_SendThisEmailImmediately';

    /**
     * Override this function for your template(s)!
     *
     * For each template you like to render, you need to supply the array with variables that can be passed to the twig renderer.
     * Those variables can then be used in the twig-template => {{ logo_png }}
     *
     * In this function you should fill a set of variables for each template.
     *
     * @param string $template the template id in standard-notation, without the ending ( .txt.twig) => "AcmeFooBundle:bar:default"
     *
     * @return array
     */
    protected function getParamArrayFor($template)
    {
        // this implementation uses the same array for all templates.
        // override this function with a more sophisticated logic
        // if you need different styles for different templates.

        $newVars = array();

        // add template-specific stuff.
        if (self::NOTIFICATIONS_TEMPLATE == $template) {
            $newVars['subject'] = 'Your notifications sent by AzineEmailBundle';
        }

        if (self::NEWSLETTER_TEMPLATE == $template) {
            $newVars['subject'] = 'Newsletter sent by AzineEmailBundle';
        }

        // send some mails immediately instead of spooled
        if (self::FOS_USER_PWD_RESETTING_TEMPLATE == $template
            || self::FOS_USER_REGISTRATION_TEMPLATE == $template
            || self::FOS_USER_CONFIRM_EMAIL_UPDATE == $template
            || self::EMAIL_UPDATE_CONFIRMATION_TEMPLATE == $template
        ) {
            $newVars[self::SEND_IMMEDIATELY_FLAG] = true;
        }

        // add generic stuff needed for all templates

        // add images to be encoded and attached to the email
        // the absolute file-path will be replaced with the CID of
        // the attached image, so it will be rendered in the html-email.
        // => to render the logo inside your twig-template you can write:
        // <html><body> ... <img src="{{ logo_png }}" alt="logo"> ... </body></html>
        $imagesDir = $this->getTemplateImageDir();
        $newVars['logo_png'] = $imagesDir.'logo.png';
        $newVars['bottom_shadow_png'] = $imagesDir.'bottomshadow.png';
        $newVars['top_shadow_png'] = $imagesDir.'topshadow.png';
        $newVars['left_shadow_png'] = $imagesDir.'left-shadow.png';
        $newVars['right_shadow_png'] = $imagesDir.'right-shadow.png';
        $newVars['placeholder_png'] = $imagesDir.'placeholder.png';

        // define some colors to be reused in the following style-definitions
        $azGreen = 'green';
        $azBlue = 'blue';
        $blackColor = 'black';
        $lightGray = '#EEEEEE';

        // add html-styles for your html-emails
        // css does not work in html-emails, so all styles need to be
        // embeded directly into the html-elements
        $newVars['azGreen'] = $azGreen;
        $newVars['azBlue'] = $azBlue;
        $newVars['blackColor'] = $blackColor;
        $newVars['lightGray'] = $lightGray;
        $newVars['bodyBackgroundColor'] = '#fdfbfa';
        $newVars['contentBackgroundColor'] = '#f2f1f0';
        $fontFamily = 'Arial, Helvetica, sans-serif';
        $newVars['fontFamily'] = $fontFamily;
        $newVars['emailWidth'] = 640; // width for the whole email-body
        $newVars['shadowWidth'] = 10; // width for the shadows left and right of the content
        $newVars['contentWidth'] = 620; // width for the mail content
        $newVars['mediaQueryWidth'] = 479; // width for the media-query for mobile devices
        $newVars['mobileEmailWidth'] = 459; // width for the whole email-body for mobile devices
        $newVars['mobileContentWidth'] = 439; // width for the mail content for mobile devices
        $newVars['footerBackgroundColor'] = '#434343';

        $newVars['h1Style'] = "style='padding:0; margin:0; font:bold 30px $fontFamily; color:$azBlue; text-decoration:none;'";
        $newVars['h2Style'] = "style='padding:0; margin:0; font:bold 24px $fontFamily; color:$azBlue; text-decoration:none;'";
        $newVars['h3Style'] = "style='margin:12px 0 5px 0; font:bold 18px $fontFamily; padding:0; color:$azGreen; text-decoration:none;'";
        $newVars['h4Style'] = "style='padding:0; margin:0 0 20px 0; color:$blackColor; font-size:14px; text-decoration:none;'";
        $newVars['smallGreyStyle'] = "style='color: grey; font: $fontFamily 11px;'";
        $newVars['salutationStyle'] = "style='color:$azBlue; font:bold 16px $fontFamily;'";
        $newVars['smallerTextStyle'] = "style='font: normal 13px/18px $fontFamily;'";
        $newVars['dateStyle'] = "style='padding:0; margin:5px 0; color:$blackColor; font-weight:bold; font-size:12px;'";
        $newVars['readMoreLinkStyle'] = "style='color:$azGreen; text-decoration:none; font-size:13px;'";
        $newVars['titleLinkStyle'] = "style='text-decoration:none;'";
        $newVars['salutationStyle'] = "style='color:$azBlue; font:bold 16px Arial;'";
        $newVars['dateStyle'] = "style='padding:0; margin:5px 0; color:$blackColor; font-weight:bold; font-size:12px;'";
        $newVars['smallerTextStyle'] = "style='font: normal 13px/18px Arial, Helvetica, sans-serif;'";
        $newVars['actionButtonStyle'] = "style='font-weight: bold; background: none repeat scroll 0 0 #DF940B; border: 1px solid orange; border-radius: 5px; box-shadow: 0 1px 0 #DF940B inset; color: white; padding: 10px;'";
        $newVars['sloganStyle'] = "style='font-size: 16px; color:$blackColor; margin: 0px; padding: 0px;'";

        // some simple txt styling elements
        $newVars['txtH1Style'] = '////////////////////////////////////////////////////////////////////////////////';
        $newVars['txtH2Style'] = '================================================================================';
        $newVars['txtH3Style'] = '--------------------------------------------------------------------------------';
        $newVars['txtH4Style'] = "''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''";
        $newVars['txtHR'] = '________________________________________________________________________________';

        return $newVars;
    }

    /**
     * Override this function for your template(s) if you use other "snippets" with embedded images.
     *
     * This function adds more complex elements to the array of variables that are passed
     * to the twig-renderer, just before sending the mail.
     *
     * In this implementation for example some reusable "snippets" are added to render
     * a nice shadow around content parts and add a "link to top" at the top of each part.
     *
     * As these "snippets" contain references to images that first had to be embedded into the
     * Message, these "snippets" are added after embedding/adding the attachments.
     *
     * This means, that here the variable "bottom_shadow_png" defined in AzineTemplateProvider.fillParamArrayFor()
     * does not contain the path to the image-file anymore but now contains the CID of the embedded image.
     *
     * @param string $template    the template id in standard-notation, without the ending ( .txt.twig) => "AcmeFooBundle:bar:default"
     * @param array  $vars
     * @param string $emailLocale
     *
     * @throws \Exception
     *
     * @return array of strings
     */
    protected function getSnippetArrayFor($template, array $vars, $emailLocale)
    {
        // this implementation uses the same snippets for all templates.
        // override this function with a more sophisticated logic
        // if you need different snippets for different templates.

        // the snippets added in this implementation depend on the
        // following images, so they must be present in the $vars-array
        if (
                !array_key_exists('bottom_shadow_png', $vars) ||
                !array_key_exists('top_shadow_png', $vars) ||
                !array_key_exists('left_shadow_png', $vars) ||
                !array_key_exists('right_shadow_png', $vars)
        ) {
            throw new \Exception('some required images are not yet added to the template-vars array.');
        }

        $snippets = array();

        // define some vars that are used several times
        $lightGray = $vars['lightGray'];
        $blackColor = $vars['blackColor'];
        $upLinkTitle = $this->getTranslator($emailLocale)->trans('html.email.go.to.top.link.label', array(), 'messages', $emailLocale);
        $fontFamily = $vars['fontFamily'];

        // create and add html-elements for easy reuse in the twig-templates
        $snippets['linkToTop'] = "<a href='#top' style='text-decoration:none;color:$blackColor' title='$upLinkTitle'>Λ</a>";
        $snippets['tableOpen'] = "<table summary='box with shadows' class='emailWidth' width='".$vars['emailWidth']."' border='0' align='center' cellpadding='0' cellspacing='0'  style='font: normal 14px/18px $fontFamily;'>";
        $snippets['topShadow'] = $snippets['tableOpen']."<tr><td class='emailWidth'  colspan='3' width='".$vars['emailWidth']."'><img class='emailWidth' width='".$vars['emailWidth']."' height='10' src='".$vars['top_shadow_png']."' alt='' style='vertical-align: bottom;'/></td></tr>";
        $snippets['leftShadow'] = "<tr><td width='10' style='border-right: 1px solid $lightGray; background-image: url(\"".$vars['left_shadow_png']."\");'>&nbsp;</td>";
        $snippets['rightShadow'] = "<td width='10' style='border-left: 1px solid $lightGray; background-image: url(\"".$vars['right_shadow_png']."\");'>&nbsp;</td></tr>";
        $snippets['bottomShadow'] = "	<tr><td colspan='3' class='emailWidth' width='".$vars['emailWidth']."'><img src='".$vars['bottom_shadow_png']."' class='emailWidth' width='".$vars['emailWidth']."' height='10' alt='' style='vertical-align: top;'/></td></tr></table>";
        $snippets['linkToTopRow'] = $snippets['leftShadow']."<td width='610' bgcolor='white' style='text-align: right; padding: 5px 5px 0; border-top: 1px solid $lightGray;'>".$snippets['linkToTop'].'</td>'.$snippets['rightShadow'];
        $snippets['cellSeparator'] = '</td>'.$snippets['rightShadow'].$snippets['bottomShadow'].$snippets['topShadow'].$snippets['linkToTopRow'].$snippets['leftShadow']."<td bgcolor='white' width='580' align='left' valign='top' style='padding:10px 20px 20px 20px;'>";

        return $snippets;
    }

    /**
     * Override this function to define your own campaign-parameters
     * (non-PHPdoc).
     *
     * @see Azine\EmailBundle\Services\TemplateProviderInterface::getCampaignParamsFor()
     */
    public function getCampaignParamsFor($templateId, array $params = null)
    {
        $campaignParams = array(
            $this->tracking_params_campaign_medium => 'email',
            $this->tracking_params_campaign_name => date('y-m-d'),
        );

        if (self::NEWSLETTER_TEMPLATE == $templateId) {
            $campaignParams[$this->tracking_params_campaign_source] = 'newsletter';
        } elseif (self::NOTIFICATIONS_TEMPLATE == $templateId) {
            $campaignParams[$this->tracking_params_campaign_source] = 'mailnotify';
        } elseif (self::CONTENT_ITEM_MESSAGE_TEMPLATE == $templateId) {
            $campaignParams[$this->tracking_params_campaign_content] = 'message';

        // don't track password-reset emails
        } elseif (self::FOS_USER_PWD_RESETTING_TEMPLATE == $templateId || self::FOS_USER_REGISTRATION_TEMPLATE == $templateId) {
            $campaignParams = array();
        }

        return $campaignParams;
    }

    /**
     * Override this function if you want to add extra headers to the messages sent.
     * (non-PHPdoc).
     *
     * @see Azine\EmailBundle\Services.TemplateProviderInterface::addCustomHeaders()
     */
    public function addCustomHeaders($template, \Swift_Message $message, array $params)
    {
        //$headerSet = $message->getHeaders();
        //$headerSet->addTextHeader($name, $vale);
    }

    /**
     * (non-PHPdoc).
     *
     * @see Azine\EmailBundle\Services.TemplateProviderInterface::saveWebViewFor()
     */
    public function saveWebViewFor($template)
    {
        if (false !== array_search($template, $this->getTemplatesToStoreForWebView())) {
            return true;
        }

        return false;
    }

    /**
     * Override this function to define which emails you want to make the web-view available and for which not.
     *
     * @return array of string => the template id in standard-notation, without the ending ( .txt.twig) => "AcmeFooBundle:bar:default"
     */
    protected function getTemplatesToStoreForWebView()
    {
        $include = array();
        $include[] = self::NEWSLETTER_TEMPLATE;

        return $include;
    }

    /**
     * Only override this method if you want to change the ID used in the twig-template for the web-view-link from 'azineEmailWebViewToken' to something else.
     * (non-PHPdoc).
     *
     * @see Azine\EmailBundle\Services.TemplateProviderInterface::getWebViewTokenId()
     */
    public function getWebViewTokenId()
    {
        return self::EMAIL_WEB_VIEW_TOKEN;
    }

    //////////////////////////////////////////////////////////////////////////
    /* You probably don't need to change or override any of the stuff below */
    //////////////////////////////////////////////////////////////////////////

    const CONTENT_ITEMS = 'contentItems';
    const EMAIL_WEB_VIEW_TOKEN = 'azineEmailWebViewToken';

    /**
     * Full filesystem-path to the directory where you store your email-template images.
     *
     * @var string
     */
    private $templateImageDir;

    /**
     * List of directories from which images are allowed to be embeded into emails.
     *
     * @var array of string
     */
    private $allowedImageFolders = array();

    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * Array to store all the arrays for the variables/parameters for all the different templates.
     *
     * @var array of array
     */
    protected $paramArrays = array();

    /**
     * @var string
     */
    protected $tracking_params_campaign_source;

    /**
     * @var string
     */
    protected $tracking_params_campaign_medium;

    /**
     * @var string
     */
    protected $tracking_params_campaign_content;

    /**
     * @var string
     */
    protected $tracking_params_campaign_name;

    /**
     * @var string
     */
    protected $tracking_params_campaign_term;

    /**
     * Array to store all the arrays for the code snippets for all the different temlpates.
     *
     * @var unknown_type
     */
    protected $snippetArrays = array();

    public function __construct(UrlGeneratorInterface $router, TranslatorInterface $translator, array $parameters)
    {
        $this->router = $router;
        $this->translator = $translator;
        $templateImageDir = realpath($parameters[AzineEmailExtension::TEMPLATE_IMAGE_DIR]);
        if (false !== $this->templateImageDir) {
            $this->templateImageDir = $templateImageDir.'/';
        }

        foreach ($parameters[AzineEmailExtension::ALLOWED_IMAGES_FOLDERS] as $nextFolder) {
            $imageFolder = realpath($nextFolder);
            if (false !== $imageFolder) {
                $this->allowedImageFolders[md5($imageFolder)] = $imageFolder.'/';
            }
        }
        $this->allowedImageFolders[md5($this->templateImageDir)] = $this->templateImageDir;
        $this->tracking_params_campaign_content = $parameters[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT];
        $this->tracking_params_campaign_medium = $parameters[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM];
        $this->tracking_params_campaign_name = $parameters[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME];
        $this->tracking_params_campaign_source = $parameters[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE];
        $this->tracking_params_campaign_term = $parameters[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM];
    }

    /**
     * (non-PHPdoc).
     *
     * @see Azine\EmailBundle\Services.TemplateProviderInterface::getTemplateImageDir()
     */
    public function getTemplateImageDir()
    {
        return $this->templateImageDir;
    }

    /**
     * (non-PHPdoc).
     *
     * @see Azine\EmailBundle\Services.TemplateProviderInterface::addTemplateVariablesFor()
     */
    public function addTemplateVariablesFor($template, array $contentVariables)
    {
        if (!array_key_exists($template, $this->paramArrays)) {
            $this->paramArrays[$template] = $this->getParamArrayFor($template);
        }

        // add vars for main template
        $contentVariables = array_merge($this->paramArrays[$template], $contentVariables);

        // add the template-variables to the contentItem-params-arrays
        if (array_key_exists(self::CONTENT_ITEMS, $contentVariables)) {
            foreach ($contentVariables[self::CONTENT_ITEMS] as $key => $contentItem) {
                // get the key (=> template)
                reset($contentItem);
                $itemTemplate = key($contentItem);

                // get the params
                $itemParams = $contentItem[$itemTemplate];

                // add params for this template
                $contentItem[$itemTemplate] = $this->addTemplateVariablesFor($itemTemplate, $itemParams);

                // store back into the main array
                $contentVariables[self::CONTENT_ITEMS][$key] = $contentItem;
            }
        }

        return $contentVariables;
    }

    /**
     * (non-PHPdoc).
     *
     * @see Azine\EmailBundle\Services.TemplateProviderInterface::addTemplateSnippetsWithImagesFor()
     */
    public function addTemplateSnippetsWithImagesFor($template, array $vars, $emailLocale, $forWebView = false)
    {
        $channel = $forWebView ? 'webView' : 'email';
        $templateKey = $channel.$template.$emailLocale;

        if (!array_key_exists($templateKey, $this->snippetArrays)) {
            $this->snippetArrays[$templateKey] = $this->getSnippetArrayFor($template, $vars, $emailLocale);
        }

        // add vars for main template
        $vars = array_merge($this->snippetArrays[$templateKey], $vars);

        // add the template-code-snippets to the contentItem-params-arrays
        if (array_key_exists(self::CONTENT_ITEMS, $vars)) {
            foreach ($vars[self::CONTENT_ITEMS] as $key => $contentItem) {
                // get the key (=> template)
                reset($contentItem);
                $itemTemplate = key($contentItem);

                // get the params
                $itemParams = $contentItem[$itemTemplate];

                // add params for this template
                $contentItem[$itemTemplate] = $this->addTemplateSnippetsWithImagesFor($itemTemplate, $itemParams, $emailLocale, $forWebView);

                // store back into the main array
                $vars[self::CONTENT_ITEMS][$key] = $contentItem;
            }
        }

        return $vars;
    }

    /**
     * @return UrlGeneratorInterface
     */
    protected function getRouter()
    {
        return $this->router;
    }

    /**
     * Only use the translator here when you already know in which language the user should get the email.
     *
     * @param string $emailLocale
     *
     * @throws \Exception
     *
     * @return Translator
     */
    protected function getTranslator($emailLocale)
    {
        if (null === $emailLocale) {
            throw new \Exception('Only use the translator here when you already know in which language the user should get the email.');
        }

        return $this->translator;
    }

    /**
     * Recursively replace all absolute image-file-paths with relative web-paths.
     *
     * @param array  $emailVars
     * @param string $locale
     *
     * @return array
     */
    public function makeImagePathsWebRelative(array $emailVars, $locale)
    {
        foreach ($emailVars as $key => $value) {
            if (is_string($value) && is_file($value)) {
                // check if the file is in an allowed_images_folder
                $folderKey = $this->isFileAllowed($value);
                if (false !== $folderKey) {
                    // replace the fs-path with the web-path
                    $fsPathToReplace = $this->getFolderFrom($folderKey);
                    $filename = substr($value, strlen($fsPathToReplace));
                    $newValue = $this->getRouter()->generate('azine_email_serve_template_image', array('folderKey' => $folderKey, 'filename' => urlencode($filename), '_locale' => $locale));
                    $emailVars[$key] = $newValue;
                }
            } elseif (is_array($value)) {
                $emailVars[$key] = $this->makeImagePathsWebRelative($value, $locale);
            }
        }

        return $emailVars;
    }

    /**
     * (non-PHPdoc).
     *
     * @see Azine\EmailBundle\Services.TemplateProviderInterface::isFileAllowed()
     */
    public function isFileAllowed($filePath)
    {
        $filePath = realpath($filePath);
        foreach ($this->allowedImageFolders as $key => $nextFolder) {
            if (0 === strpos($filePath, $nextFolder)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * (non-PHPdoc).
     *
     * @see Azine\EmailBundle\Services.TemplateProviderInterface::getFolderFrom()
     */
    public function getFolderFrom($key)
    {
        if (array_key_exists($key, $this->allowedImageFolders)) {
            return $this->allowedImageFolders[$key];
        }

        return false;
    }
}
