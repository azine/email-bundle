<?php
namespace Azine\EmailBundle\Services;

/**
 *
 * @author dominik
 *
 */
interface TemplateTwigSwiftMailerInterface {

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
	 * @param \Swift_Message $message instance of \Swift_Message that can be accessed by reference after sending the email.
	 * @throws FileException
	 * @return number of sent messages
	 */
	public function sendEmail(&$failedRecipients, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, array $params, $template, $attachments = array(), $emailLocale = null, \Swift_Message &$message = null);

	/**
	 * Convenience function to send one email, no attachments, x recipient
	 * @param string or array of strings $to => email-addresses
	 * @param string $toName will be ignored it $to is an array
	 * @param array $params
	 * @param string $template
	 * @param string $emailLocale
	 * @param string $from defaults to azine's mailer
	 * @param string $fromName defaults to azine's mailer
	 * @param \Swift_Message $message instance of \Swift_Message that can be accessed by reference after sending the email.
	 * @return boolean true if the mail was sent successfully, else false
	 */
	public function sendSingleEmail($to, $toName, array $params, $template, $emailLocale, $from = null, $fromName = null, \Swift_Message &$message = null);

}