<?php

namespace Azine\EmailBundle\Services;

/**
 * @author dominik
 */
interface TemplateTwigSwiftMailerInterface
{
    /**
     * This send the txt- and html-email rendered from the $template using the parameters in $params.
     *
     * @param array          $failedRecipients modified by reference, so after the function returns, the array contains the failed email-addresses
     * @param string         $subject
     * @param string         $from             Email
     * @param string         $fromName
     * @param string|array   $to               Email
     * @param string         $toName           if $to is an array, $toName will be ignored
     * @param string|array   $cc               Email
     * @param string         $ccName           if $cc is an array, $ccName will be ignored
     * @param string|array   $bcc              Email
     * @param string         $bccName          if $bcc is an array, $bccName will be ignored
     * @param string|array   $replyTo          Email
     * @param string         $replyToName
     * @param array          $params           associative array of variables for the twig-template
     * @param string         $template         twig-template to render, needs to have "body_text", "body_html" and "subject" blocks
     * @param array          $attachments      associative array of attachmentNames and files (url or data) (if the attachmentName for an attachment is less than 5 chars long, the original file-name is used)
     * @param string         $emailLocale      two-char locale for the rendering of the email
     * @param \Swift_Message $message          instance of \Swift_Message that can be accessed by reference after sending the email
     *
     * @throws FileException
     *
     * @return int of sent messages
     */
    public function sendEmail(&$failedRecipients, $subject, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, array $params, $template, $attachments = array(), $emailLocale = null, \Swift_Message &$message = null);

    /**
     * Convenience function to send one email, no attachments, x recipient.
     *
     * @param string|array of strings $to          => email-addresses
     * @param string                  $toName      will be ignored it $to is an array
     * @param string                  $subject
     * @param string                  $template
     * @param string                  $emailLocale
     * @param string                  $from        defaults to azine's mailer
     * @param string                  $fromName    defaults to azine's mailer
     * @param \Swift_Message          $message     instance of \Swift_Message that can be accessed by reference after sending the email
     * @param string                  $to
     *
     * @return bool true if the mail was sent successfully, else false
     */
    public function sendSingleEmail($to, $toName, $subject, array $params, $template, $emailLocale, $from = null, $fromName = null, \Swift_Message &$message = null);
}
