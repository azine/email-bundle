<?php
namespace Azine\EmailBundle\Services;


use Doctrine\ORM\EntityManager;

use Azine\EmailBundle\Entity\SentEmail;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;

use Symfony\Bundle\FrameworkBundle\Translation\Translator;

use Monolog\Logger;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use FOS\UserBundle\Mailer\TwigSwiftMailer;


/**
 * This Service is used to send html-emails with embeded images
 * @author Dominik Businger
 */
class AzineTwigSwiftMailer extends TwigSwiftMailer implements TemplateTwigSwiftMailerInterface {
	/**
	 * @var Translator
	 */
	protected $translator;

	/**
	 * @var Logger
	 */
	protected $logger;

	/**
	 * @var TemplateProviderInterface
	 */
	protected $templateProvider;

	/**
	 * @var EntityManager
	 */
	protected $entityManager;

	/**
	 * @var email to use for "no-reply"
	 */
	protected $noReplyEmail;

	/**
	 * @var name to use for "no-reply"
	 */
	protected $noReplyName;

	private $encodedItemIdPattern;
	private $currentHost;
	private $templateCache = array();
	private $imageCache = array();

	/**
	 *
	 * @param \Swift_Mailer $mailer
	 * @param UrlGeneratorInterface $router
	 * @param \Twig_Environment $twig
	 * @param Logger $logger
	 * @param Translator $translator
	 * @param EntityManager $em
	 * @param array $parameters
	 */
	public function __construct(	\Swift_Mailer $mailer,
									UrlGeneratorInterface $router,
									\Twig_Environment $twig,
									Logger $logger,
									Translator $translator,
									TemplateProviderInterface $templateProvider,
									EntityManager $entityManager,
									array $parameters)
	{
		parent::__construct($mailer, $router, $twig, $parameters);
		$this->logger = $logger;
		$this->translator = $translator;
		$this->templateProvider = $templateProvider;
		$this->entityManager = $entityManager;
		$this->noReplyEmail = $parameters[AzineEmailExtension::NO_REPLY][AzineEmailExtension::NO_REPLY_EMAIL_ADDRESS];
		$this->noReplyName = $parameters[AzineEmailExtension::NO_REPLY][AzineEmailExtension::NO_REPLY_EMAIL_NAME];
		$this->currentHost = $router->getContext()->getHost();
		$this->encodedItemIdPattern = "/^cid:.*@/";
	}

	/**
	 * This send the txt- and html-email rendered from the $template using the parameters in $params
	 *
	 * @param array $failedRecipients modified by reference, so after the function returns, the array contains the failed email-addresses.
	 * @param String $from Email
	 * @param String $fromName
	 * @param String or array $to Email
	 * @param String or null $toName if $to is an array, $toName will be ignored
	 * @param String or array $cc Email
	 * @param String or null $ccName if $cc is an array, $ccName will be ignored
	 * @param String or array $bcc Email
	 * @param String or null $bccName if $bcc is an array, $bccName will be ignored
	 * @param String or array $replyTo Email
	 * @param String or null $replyToName
	 * @param array $params associative array of variables for the twig-template
	 * @param string $template twig-template to render, needs to have "body_text", "body_html" and "subject" blocks
	 * @param array $attachments associative array of attachmentNames and files (url or data) (if the attachmentName for an attachment is less than 5 chars long, the original file-name is used)
	 * @param string $emailLocale two-char locale for the rendering of the email
	 * @throws FileException
	 * @return number of sent messages
	 */
	public function sendEmail(&$failedRecipients, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, array $params, $template, $attachments = array(), $emailLocale = null){

		// create the message
		$message = \Swift_Message::newInstance();

		// add the from-email for the footer-text
		if(!array_key_exists('fromEmail', $params)){
			$params['sendMailAccountName'] = $this->noReplyName;
			$params['sendMailAccountAddress'] = $this->noReplyEmail;
		}

		// get the baseTemplate. => templateId without the ending.
		$templateBaseId = substr($template, 0, strrpos($template, ".", -6));

		// check if this email should be stored for web-view
		if($this->templateProvider->saveWebViewFor($templateBaseId)){
			// keep a copy of the vars for the web-view
			$webViewParams = $params;

			// add the web-view token
			$params[$this->templateProvider->getWebViewTokenId()] = SentEmail::getNewToken();
		}

		// recursively add all template-variables for the wrapper-templates and contentItems
		$params = $this->templateProvider->addTemplateVariablesFor($templateBaseId, $params);

		// recursively attach all messages in the array
		$this->embedImages($message, $params);

		// change the locale for the email-recipients
		if($emailLocale != null){
			$currentUserLocale = $this->translator->getLocale();
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
		$subject = $twigTemplate->renderBlock('subject', $params);
		$message->setSubject($subject);

		$textBody = $twigTemplate->renderBlock('body_text', $params);
		$message->addPart($textBody, 'text/plain');

		$htmlBody = $twigTemplate->renderBlock('body_html', $params);

		$campaignParams = $this->templateProvider->getCampaignParamsFor($templateBaseId, $params);

		if(sizeof($campaignParams) > 0){
			$htmlBody = $this->addCampaignParamsToAllUrls($htmlBody, $campaignParams);
		}

		$message->setBody($htmlBody, 'text/html');

		// remove unused/unreferenced embeded items from the message
		$message = $this->removeUnreferecedEmbededItemsFromMessage($message, $params, $htmlBody);

		// change the locale back to the users locale
		if(isset($currentUserLocale) && $currentUserLocale != null){
			$this->translator->setLocale($currentUserLocale);
		}

		// add attachments
		foreach ($attachments as $fileName => $file){

			// add attachment from existing file
			if(is_string($file)){

				// check that the file really exists!
				if(file_exists($file)){
					$attachment = \Swift_Attachment::fromPath($file);
					if(strlen($fileName) >= 5 ){
						$attachment->setName($fileName);
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
		if($from){
			$message->setFrom($this->noReplyEmail, $this->noReplyName);
			$message->setReplyTo($from, $fromName);
		}
		if($to){
			$message->setTo($to, $toName);
		}
		if($cc){
			$message->setCc($cc, $ccName);
		}
		if($bcc){
			$message->setBcc($bcc, $bccName);
		}

		// send the message
		$messagesSent = $this->mailer->send($message, $failedRecipients);

		// if the message was successfully sent,
		// and it should be made available in web-view
		if($messagesSent && array_key_exists($this->templateProvider->getWebViewTokenId(), $params)){

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
			if(!is_array($to)){
				$to = array($to);
			}
			$successfulRecipients = array_diff($to, $failedRecipients);
			$sentEmail->setRecipients($successfulRecipients);

			// write to db
			$this->entityManager->persist($sentEmail);
			$this->entityManager->flush($sentEmail);
		}

		return $messagesSent;
	}

	private function addCampaignParamsToAllUrls($htmlBody, $campaignParams){

		$urlPattern = '/(href=[\'|"])(http[s]?\:\/\/\S*)([\'|"])/';

		$filteredHtmlBody = preg_replace_callback($urlPattern, function($matches) use ($campaignParams){
																	$start = $matches[1];
																	$url = $matches[2];
																	$end = $matches[3];

																	$urlParams = http_build_query($campaignParams);

																	if(strpos($url,"?") === false){
																		$urlParams = "?".$urlParams;
																	} else {
																		$urlParams = "&".$urlParams;
																	}

																	$replacement = $start.$url.$urlParams.$end;
																	return $replacement;

																}, $htmlBody);

		return $filteredHtmlBody;
	}

	/**
	 * Remove all Embeded Attachments that are not referenced in the html-body from the message
	 * to avoid using unneccary bandwidth.
	 *
	 * @param \Swift_Message $message
	 * @param array $params the parameters used to render the html
	 * @param string $htmlBody
	 */
	private function removeUnreferecedEmbededItemsFromMessage(\Swift_Message $message, $params, $htmlBody){

		foreach ($params as $key => $value){
			// remove unreferenced attachments from contentItems too.
			if($key == 'contentItems'){
				foreach ($value as $contentItemParams){
					$message = $this->removeUnreferecedEmbededItemsFromMessage($message, $contentItemParams, $htmlBody);
				}
			} else {

				// check if the embeded items are referenced in the templates
				$isEmbededItem = is_string($value) && preg_match($this->encodedItemIdPattern, $value) == 1;

				if($isEmbededItem && stripos($htmlBody, $value) === false){
					// remove unreferenced items
					$children = array();

					foreach($message->getChildren() as $attachment){
						if("cid:".$attachment->getId() != $value){
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
	 * @param string $template
	 * @return \Twig_Template
	 */
	private function loadTemplate($template){
		if(!array_key_exists($template, $this->templateCache)){
			$this->templateCache[$template] = $this->twig->loadTemplate($template);
		}
		return $this->templateCache[$template];
	}

	/**
	 * Recursively embed all images in the array into the message
	 * @param Swift_Message $message
	 * @param array $params
	 * @return $params
	 */
	private function embedImages(&$message, &$params){
		// loop throug the array
		foreach ($params as $key => $value){

			// if the current value is an array
			if(is_array($value)){
				// search for more images deeper in the arrays
				$value = $this->embedImages($message, $value);
				$params[$key] = $value;

			// if the current value is an existing file from the image-folder, embed it
			} else if(is_string($value) && is_file($value)){

				// check if the file is from an allowed folder
				if($this->templateProvider->isFileAllowed($value) !== false){
					$encodedImage = $this->cachedEmbedImage($value);
					if($encodedImage != null){
						$id = $message->embed($encodedImage);
						$params[$key] = $id;
					}
				}

				//if the current value is a generated image
			} else if(is_resource($value) && stripos(get_resource_type($value), "gd") == 0){
				$encodedImage = \Swift_Image::newInstance($value, "generatedImage".md5(time()).rand(0, 1000));
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
	 * @param string $filePath
	 * @return \Swift_Image|null
	 */
	private function cachedEmbedImage($filePath){
		$encodedImage = null;
		$filePath = realpath($filePath);
		if(!array_key_exists($filePath, $this->imageCache)){
			if(is_file($filePath)){

				$image = \Swift_Image::fromPath($filePath);
				$id = $image->getId();

				// log an error if the image could not be embedded properly
				if(	$id == $filePath ){		// $id and $value must not be the same => this happens if the file cannot be found/read
					// log error
					$this->logger->error('The image $value was not correctly embedded in the email.', array('image' => $filePath, 'resulting id' => $id));
					// add a null-value to the cache for this path, so we don't try again.
					$this->imageCache[$filePath] = null;

				} else {
					// add the image to the cache
					$this->imageCache[$filePath] = $image;
				}

			// the $filePath isn't a regular file
			} else {
				// ignore the imageDir itself, but log all other directories and symlinks that were not embeded
				if ($filePath != $this->templateProvider->getTemplateImageDir() ){
					$this->logger->info("'$filePath' is not a regular file and will not be embeded in the email.");
				}

				// add a null-value to the cache for this path, so we don't try again.
				$this->imageCache[$filePath] = null;
			}

		}
		return $this->imageCache[$filePath];
	}

	/**
	 * Convenience function to send one email, no attachments, x recipient
	 * @param string or array of strings $to => email-addresses
	 * @param string $toName will be ignored it $to is an array
	 * @param string $from defaults to azine's mailer
	 * @param string $fromName defaults to azine's mailer
	 * @param array $params
	 * @param string $template
	 * @param string $emailLocale
	 * @return boolean true if the mail was sent successfully, else false
	 */
	public function sendSingleEmail($to, $toName, $params, $template, $emailLocale, $from = null, $fromName = null){
		if($from == null){
			$from = $this->noReplyEmail;
			$fromName = $this->noReplyName;
		}
		$failedRecipients = array();
		$this->sendEmail($failedRecipients, $from, $fromName, $to, $toName, null, null, null, null, null, null, $params, $template, array(), $emailLocale);

		return sizeof($failedRecipients) == 0;
	}

    /**
     * Override the fosuserbundles original sendMessage, to embed template variables etc. into html-emails.
     * @param string $templateName
     * @param array  $context
     * @param string $fromEmail
     * @param string $toEmail
	 * @return boolean true if the mail was sent successfully, else false
     */
    protected function sendMessage($templateName, $context, $fromEmail, $toEmail)
    {
    	return $this->sendSingleEmail($toEmail, null, $context, $templateName, $this->translator->getLocale());
    }
}
