<?php
namespace Azine\EmailBundle\Services;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use FOS\UserBundle\Mailer\TwigSwiftMailer;
use Azine\EmailBundle\Entity\RecipientInterface;

/**
 * This Service is used to send html-emails with embedded images
 * @author Dominik Businger
 */
class AzineTwigSwiftMailer extends TwigSwiftMailer implements TemplateTwigSwiftMailerInterface
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var TemplateProviderInterface
     */
    protected $templateProvider;

    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    /**
     *
     * @var RequestContext
     */
    protected $routerContext;

    /**
     * @var string email to use for "no-reply"
     */
    protected $noReplyEmail;

    /**
     * @var string name to use for "no-reply"
     */
    protected $noReplyName;

    /**
     * The Swift_Mailer to be used for sending emails immediately
     * @var \Swift_Mailer
     */
    private $immediateMailer;

    /**
     * @var EmailOpenTrackingCodeBuilderInterface
     */
    private $emailOpenTrackingCodeBuilder;

    /**
     * @var AzineEmailTwigExtension
     */
    private $emailTwigExtension;

    private $encodedItemIdPattern;
    private $templateCache = array();
    private $imageCache = array();


    /**
     *
     * @param \Swift_Mailer $mailer
     * @param UrlGeneratorInterface $router
     * @param \Twig_Environment $twig
     * @param Translator $translator
     * @param TemplateProviderInterface $templateProvider
     * @param ManagerRegistry $managerRegistry
     * @param EmailOpenTrackingCodeBuilderInterface $emailOpenTrackingCodeBuilder
     * @param AzineEmailTwigExtension $emailTwigExtension
     * @param array $parameters
     * @param \Swift_Mailer $immediateMailer
     */
    public function __construct(    \Swift_Mailer $mailer,
                                    UrlGeneratorInterface $router,
                                    \Twig_Environment $twig,
                                    Translator $translator,
                                    TemplateProviderInterface $templateProvider,
                                    ManagerRegistry $managerRegistry,
                                    EmailOpenTrackingCodeBuilderInterface $emailOpenTrackingCodeBuilder,
                                    AzineEmailTwigExtension $emailTwigExtension,
                                    array $parameters,
                                    \Swift_Mailer $immediateMailer = null)
    {
        parent::__construct($mailer, $router, $twig, $parameters);
        $this->immediateMailer = $immediateMailer;
        $this->translator = $translator;
        $this->templateProvider = $templateProvider;
        $this->managerRegistry = $managerRegistry;
        $this->noReplyEmail = $parameters[AzineEmailExtension::NO_REPLY][AzineEmailExtension::NO_REPLY_EMAIL_ADDRESS];
        $this->noReplyName = $parameters[AzineEmailExtension::NO_REPLY][AzineEmailExtension::NO_REPLY_EMAIL_NAME];
        $this->emailOpenTrackingCodeBuilder = $emailOpenTrackingCodeBuilder;
        $this->routerContext = $router->getContext();
        $this->encodedItemIdPattern = "/^cid:.*@/";
        $this->emailTwigExtension = $emailTwigExtension;
    }

    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.TemplateTwigSwiftMailerInterface::sendEmail()
     * @param array $failedRecipients
     * @param string $subject
     * @param String $from
     * @param String $fromName
     * @param array|String $to
     * @param String $toName
     * @param array|String $cc
     * @param String $ccName
     * @param array|String $bcc
     * @param String $bccName
     * @param $replyTo
     * @param $replyToName
     * @param array $params
     * @param $template
     * @param array $attachments
     * @param null $emailLocale
     * @param \Swift_Message $message
     * @return int
     */
    public function sendEmail(&$failedRecipients, $subject, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, array $params, $template, $attachments = array(), $emailLocale = null, \Swift_Message &$message = null)
    {
        // create the message
        if ($message === null) {
            $message = \Swift_Message::newInstance();
        }

        $message->setSubject($subject);

        // set the from-Name & -Email to the default ones if not given
        if ($from === null) {
            $from = $this->noReplyEmail;
            if ($fromName === null) {
                $fromName = $this->noReplyName;
            }
        }

        // add the from-email for the footer-text
        if (!array_key_exists('fromEmail', $params)) {
            $params['sendMailAccountName'] = $this->noReplyName;
            $params['sendMailAccountAddress'] = $this->noReplyEmail;
        }

        // get the baseTemplate. => templateId without the ending.
        $templateBaseId = substr($template, 0, strrpos($template, ".", -6));

        // check if this email should be stored for web-view
        if ($this->templateProvider->saveWebViewFor($templateBaseId)) {
            // keep a copy of the vars for the web-view
            $webViewParams = $params;

            // add the web-view token
            $params[$this->templateProvider->getWebViewTokenId()] = SentEmail::getNewToken();
        } else {
            $webViewParams = array();
        }

        // recursively add all template-variables for the wrapper-templates and contentItems
        $params = $this->templateProvider->addTemplateVariablesFor($templateBaseId, $params);

        // recursively attach all messages in the array
        $this->embedImages($message, $params);

        // change the locale for the email-recipients
        if ($emailLocale !== null && strlen($emailLocale) > 0) {
            $currentUserLocale = $this->translator->getLocale();

            // change the router-context locale
            $this->routerContext->setParameter("_locale", $emailLocale);

            // change the translator locale
            $this->translator->setLocale($emailLocale);
        } else {
            $emailLocale = $this->translator->getLocale();
        }

        // recursively add snippets for the wrapper-templates and contentItems
        $params = $this->templateProvider->addTemplateSnippetsWithImagesFor($templateBaseId, $params, $emailLocale);

        // add the emailLocale (used for web-view)
        $params['emailLocale'] = $emailLocale;

        // render the email parts
        $twigTemplate = $this->loadTemplate($template);
        $textBody = $twigTemplate->renderBlock('body_text', $params);
        $message->addPart($textBody, 'text/plain');

        $htmlBody = $twigTemplate->renderBlock('body_html', $params);

        $campaignParams = $this->templateProvider->getCampaignParamsFor($templateBaseId, $params);

        if (sizeof($campaignParams) > 0) {
            $htmlBody = $this->emailTwigExtension->addCampaignParamsToAllUrls($htmlBody, $campaignParams);
        }

        // if email-tracking is enabled
        if($this->emailOpenTrackingCodeBuilder){
            // add an image at the end of the html tag with the tracking-params to track email-opens
            $imgTrackingCode = $this->emailOpenTrackingCodeBuilder->getTrackingImgCode($templateBaseId, $campaignParams, $params, $message->getId(), $to, $cc, $bcc);
            if($imgTrackingCode && strlen($imgTrackingCode) > 0) {
                $htmlCloseTagPosition = strpos($htmlBody, "</body>");
                $htmlBody = substr_replace($htmlBody, $imgTrackingCode, $htmlCloseTagPosition, 0);
            }
        }

        $message->setBody($htmlBody, 'text/html');

        // remove unused/unreferenced embeded items from the message
        $message = $this->removeUnreferecedEmbededItemsFromMessage($message, $params, $htmlBody);

        // change the locale back to the users locale
        if (isset($currentUserLocale) && $currentUserLocale !== null) {
            $this->routerContext->setParameter("_locale", $currentUserLocale);
            $this->translator->setLocale($currentUserLocale);
        }

        // add attachments
        foreach ($attachments as $fileName => $file) {

            // add attachment from existing file
            if (is_string($file)) {

                // check that the file really exists!
                if (file_exists($file)) {
                    $attachment = \Swift_Attachment::fromPath($file);
                    if (strlen($fileName) >= 5 ) {
                        $attachment->setFilename($fileName);
                    }
                } else {
                    throw new FileException("File not found: ".$file);
                }

                // add attachment from generated data
            } else {
                $attachment = \Swift_Attachment::newInstance($file, $fileName);
            }

            $message->attach($attachment);
        }

        // set the addresses
        if ($from) {
            $message->setFrom($from, $fromName);
        }
        if ($replyTo) {
            $message->setReplyTo($replyTo, $replyToName);
        } elseif ($from) {
            $message->setReplyTo($from, $fromName);
        }
        if ($to) {
            $message->setTo($to, $toName);
        }
        if ($cc) {
            $message->setCc($cc, $ccName);
        }
        if ($bcc) {
            $message->setBcc($bcc, $bccName);
        }

        // add custom headers
        $this->templateProvider->addCustomHeaders($templateBaseId, $message, $params);

        // send the message
        $mailer = $this->getMailer($params);
        $messagesSent = $mailer->send($message, $failedRecipients);

        // if the message was successfully sent,
        // and it should be made available in web-view
        if ($messagesSent && array_key_exists($this->templateProvider->getWebViewTokenId(), $params)) {

            // store the email
            $sentEmail = new SentEmail();
            $sentEmail->setToken($params[$this->templateProvider->getWebViewTokenId()]);
            $sentEmail->setTemplate($templateBaseId);
            $sentEmail->setSent(new \DateTime());

            // recursively add all template-variables for the wrapper-templates and contentItems
            $webViewParams = $this->templateProvider->addTemplateVariablesFor($template, $webViewParams);

            // replace absolute image-paths with relative ones.
            $webViewParams = $this->templateProvider->makeImagePathsWebRelative($webViewParams, $emailLocale);

            // recursively add snippets for the wrapper-templates and contentItems
            $webViewParams = $this->templateProvider->addTemplateSnippetsWithImagesFor($template, $webViewParams, $emailLocale, true);

            $sentEmail->setVariables($webViewParams);

            // save only successfull recipients
            if (!is_array($to)) {
                $to = array($to);
            }
            $successfulRecipients = array_diff($to, $failedRecipients);
            $sentEmail->setRecipients($successfulRecipients);

            // write to db
            $em = $this->managerRegistry->getManager();
            $em->persist($sentEmail);
            $em->flush($sentEmail);
            $em->clear();
            gc_collect_cycles();
        }

        return $messagesSent;
    }

    /**
     * Remove all Embeded Attachments that are not referenced in the html-body from the message
     * to avoid using unneccary bandwidth.
     *
     * @param \Swift_Message $message
     * @param array $params the parameters used to render the html
     * @param string $htmlBody
     * @return \Swift_Message
     */
    private function removeUnreferecedEmbededItemsFromMessage(\Swift_Message $message, $params, $htmlBody)
    {
        foreach ($params as $key => $value) {
            // remove unreferenced attachments from contentItems too.
            if ($key === 'contentItems') {
                foreach ($value as $contentItemParams) {
                    $message = $this->removeUnreferecedEmbededItemsFromMessage($message, $contentItemParams, $htmlBody);
                }
            } else {

                // check if the embeded items are referenced in the templates
                $isEmbededItem = is_string($value) && preg_match($this->encodedItemIdPattern, $value) == 1;

                if ($isEmbededItem && stripos($htmlBody, $value) === false) {
                    // remove unreferenced items
                    $children = array();

                    foreach ($message->getChildren() as $attachment) {
                        if ("cid:".$attachment->getId() != $value) {
                            $children[] = $attachment;
                        }
                    }

                    $message->setChildren($children);
                }
            }
        }

        return $message;
    }

    /**
     * Get the template from the cache if it was loaded already
     * @param  string         $template
     * @return \Twig_Template
     */
    private function loadTemplate($template)
    {
        if (!array_key_exists($template, $this->templateCache)) {
            $this->templateCache[$template] = $this->twig->loadTemplate($template);
        }

        return $this->templateCache[$template];
    }

    /**
     * Recursively embed all images in the array into the message
     * @param  \Swift_Message $message
     * @param  array $params
     * @return array $params
     */
    private function embedImages(&$message, &$params)
    {
        // loop through the array
        foreach ($params as $key => $value) {

            // if the current value is an array
            if (is_array($value)) {
                // search for more images deeper in the arrays
                $value = $this->embedImages($message, $value);
                $params[$key] = $value;

            // if the current value is an existing file from the image-folder, embed it
            } elseif (is_string($value)) {
                if (is_file($value)) {

                    // check if the file is from an allowed folder
                    if ($this->templateProvider->isFileAllowed($value) !== false) {
                        $encodedImage = $this->cachedEmbedImage($value);
                        if ($encodedImage !== null) {
                            $id = $message->embed($encodedImage);
                            $params[$key] = $id;
                        }
                    }

                // the $filePath isn't a regular file
                } else {
                    // add a null-value to the cache for this path, so we don't try again.
                    $this->imageCache[$value] = null;
                }

                //if the current value is a generated image
            } elseif (is_resource($value) && stripos(get_resource_type($value), "gd") == 0) {
                // get the image-data as string
                ob_start();
                imagepng($value);
                $imageData = ob_get_clean();

                // encode the image
                $encodedImage = \Swift_Image::newInstance($imageData, "generatedImage".md5($imageData));
                $id = $message->embed($encodedImage);
                $params[$key] = $id;
            } else {
                // don't do anything
            }
        }

        // remove duplicate-attachments
        $message->setChildren(array_unique($message->getChildren()));

        return $params;
    }

    /**
     * Get the Swift_Image for the file.
     * @param  string            $filePath
     * @return \Swift_Image|null
     */
    private function cachedEmbedImage($filePath)
    {
        $filePath = realpath($filePath);
        if (!array_key_exists($filePath, $this->imageCache)) {
            if (is_file($filePath)) {

                $image = \Swift_Image::fromPath($filePath);
                $id = $image->getId();

                // $id and $value must not be the same => this happens if the file cannot be found/read
                if ($id == $filePath) {
                    // @codeCoverageIgnoreStart
                    // add a null-value to the cache for this path, so we don't try again.
                    $this->imageCache[$filePath] = null;

                } else {
                    // @codeCoverageIgnoreEnd
                    // add the image to the cache
                    $this->imageCache[$filePath] = $image;
                }

            }

        }

        return $this->imageCache[$filePath];
    }

    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.TemplateTwigSwiftMailerInterface::sendSingleEmail()
     * @param string $to
     * @param string $toName
     * @param string $subject
     * @param array $params
     * @param string $template
     * @param string $emailLocale
     * @param null $from
     * @param null $fromName
     * @param \Swift_Message $message
     * @return bool
     */
    public function sendSingleEmail($to, $toName, $subject, array $params, $template, $emailLocale, $from = null, $fromName = null, \Swift_Message &$message = null)
    {
        $failedRecipients = array();
        $this->sendEmail($failedRecipients, $subject, $from, $fromName, $to, $toName, null, null, null, null, null, null, $params, $template, array(), $emailLocale, $message);

        return sizeof($failedRecipients) == 0;
    }

    /**
     * Override the fosuserbundles original sendMessage, to embed template variables etc. into html-emails.
     * @param  string  $templateName
     * @param  array   $context
     * @param  string  $fromEmail
     * @param  string  $toEmail
     * @return boolean true if the mail was sent successfully, else false
     */
    protected function sendMessage($templateName, $context, $fromEmail, $toEmail)
    {
        // get the subject from the template
        // => make sure the subject block exists in your fos-templates (FOSUserBundle:Registration:email.txt.twig & FOSUserBundle:Resetting:email.txt.twig)
        $twigTemplate = $this->loadTemplate($templateName);
        $subject = $twigTemplate->renderBlock('subject', $context);

        return $this->sendSingleEmail($toEmail, null, $subject, $context, $templateName, $this->translator->getLocale(), $fromEmail);
    }

    /**
     * Return the Swift_Mailer to be used for sending mails immediately (e.g. instead of spooling them) if it is configured
     * @param $params
     * @return \Swift_Mailer
     */
    private function getMailer($params){
        // if the second mailer for immediate mail-delivery has been configured
        if($this->immediateMailer !== null){
            // check if this template has been configured to be sent immediately
            if(array_key_exists(AzineTemplateProvider::SEND_IMMEDIATELY_FLAG, $params) && $params[AzineTemplateProvider::SEND_IMMEDIATELY_FLAG]) {
                return $this->immediateMailer;
            }
        }
        return $this->mailer;
    }
}
