<?php
namespace Azine\EmailBundle\Services;

use Symfony\Bundle\FrameworkBundle\Translation\Translator;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;

use FOS\UserBundle\Mailer\TwigSwiftMailer;
use Monolog\Logger;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This Service is used to send html-emails with embeded images
 * @author Dominik Businger
 */
class AzineTwigSwiftMailer extends TwigSwiftMailer implements MailerInterface
{
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
	 * @var string with the relative path to the directory with the template images
	 */
	protected $templateImageDir;

	/**
	 * @var email to use for "no-reply"
	 */
	protected $noReplyEmail;

	/**
	 * @var name to use for "no-reply"
	 */
	protected $noReplyName;

	/**
	 *
	 * @param \Swift_Mailer $mailer
	 * @param UrlGeneratorInterface $router
	 * @param \Twig_Environment $twig
	 * @param Logger $logger
	 * @param Translator $translator
	 * @param array $parameters
	 */
	public function __construct(	\Swift_Mailer $mailer,
									UrlGeneratorInterface $router,
									\Twig_Environment $twig,
									Logger $logger,
									Translator $translator,
									TemplateProviderInterface $templateProvider,
									array $parameters)
	{
		parent::__construct($mailer, $router, $twig, $parameters);
		$this->logger = $logger;
		$this->translator = $translator;
		$this->templateProvider = $templateProvider;
		$this->templateImageDir = $parameters[AzineEmailExtension::TEMPLATE_IMAGE_DIR];
		$this->noReplyEmail = $parameters[AzineEmailExtension::NO_REPLY][AzineEmailExtension::NO_REPLY_EMAIL_ADDRESS];
		$this->noReplyName = $parameters[AzineEmailExtension::NO_REPLY][AzineEmailExtension::NO_REPLY_EMAIL_NAME];
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
	 * @param string $htmlTemplateType the template-type to use to get the template-variables from the templateProvider
	 * @param array $attachments associative array of attachmentNames and files (url or data) (if the attachmentName for an attachment is less than 5 chars long, the original file-name is used)
	 * @param string $emailLocale two-char locale for the rendering of the email
	 * @throws FileException
	 * @return number of sent messages
	 */
	public function sendEmail(&$failedRecipients, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, array $params, $template, $htmlTemplateType, array $attachments = array(), $emailLocale = null){

		// create the message
		$message = \Swift_Message::newInstance();

		// add all template-images
		$params = $this->templateProvider->addTemplateVariablesFor($htmlTemplateType, $params);

		// recursively attach all messages in the array
		$this->embedImages($message, $params);

		// add styles & blocks for the template
		$params = $this->templateProvider->addTemplateBlocksFor($htmlTemplateType, $params);

		// change the locale for the email-recipients
		if($emailLocale != null){
			$currentUserLocale = $this->translator->getLocale();
			$this->translator->setLocale($emailLocale);
		}

		// render the email parts
		$template = $this->twig->loadTemplate($template);
		$subject = $template->renderBlock('subject', $params);
		$message->setSubject($subject);

		$textBody = $template->renderBlock('body_text', $params);
		$message->addPart($textBody, 'text/plain');

		$htmlBody = $template->renderBlock('body_html', $params);
		$message->setBody($htmlBody, 'text/html');

		// remove unused/unreferenced embeded items from the message
		foreach ($params as $key => $value){
			// test all the embeded items
			// dominik check if this regexp can be improved to rule out false-positives
			if(preg_match("/^cid:.*", $value) && stripos($htmlBody, $value) === false){
				foreach($message->getChildren() as $attachment){
					$attachmentId = $attachment->getId();
					if("cid:".$attachmentId == $value){
						$message->detach($attachment);
					}
				}
			}
		}

		// change the locale back to the users locale
		if($emailLocale != null){
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
			$message->setFrom($this->fromEmail, $this->fromEmailName);
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
		return $messagesSent;
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
			} else if(is_string($value) && file_exists($value) && strpos($value, $this->getTemplateImageDir()) === 0 ){
				$encodedImage = \Swift_Image::fromPath($value);
				$id = $message->embed($encodedImage);
				$params[$key] = $id;
				// dominik fix this so it works generaly & not only for azine.me
				if($id == $value || stripos($id, "cid:") !== 0 || stripos($id, "azine.me") !== strlen($id)- 8 ){
					$this->logger->error('The image $value was not correctly embedded in the email.', array('image' => $value, 'resulting id' => $id));
				}

				//if the current value is a generated image
			} else if(is_resource($value) && stripos(get_resource_type($value), "gd") == 0){
				$encodedImage = \Swift_Image::newInstance($value, "generatedImage".md5(time()));
				$id = $message->embed($encodedImage);
				$params[$key] = $id;
			} else {
				// don't do anything
			}
		}
		return $params;
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
	 * @return \Azine\PlatformBundle\Services\number
	 */
	public function sendSingleEmail($to, $toName, $params, $template, $from = null, $fromName = null, $emailLocale = null){
		if($from == null){
			$from = $this->fromEmail;
			$fromName = $this->fromEmailName;
		}
		return $this->sendEmail($failedRecipients, $from, $fromName, $to, $toName, null, null, null, null, null, null, $params, $template, array(), $emailLocale);
	}

    /**
     * Override the fosuserbundles original sendMessage, to embed template variables etc. into html-emails.
     * @param string $templateName
     * @param array  $context
     * @param string $fromEmail
     * @param string $toEmail
     */
    protected function sendMessage($templateName, $context, $fromEmail, $toEmail)
    {
    	$this->sendSingleEmail($toEmail, null, $context, $templateName);
    }

    /**
     * Get the absolute path to the directory with the template images
     * @return string
     */
    private function getTemplateImageDir(){
    	return __DIR__."/".$this->templateImageDir;
    }
}
