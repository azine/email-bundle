<?php
namespace Azine\EmailBundle\Services;


interface EmailOpenTrackingCodeBuilderInterface {

    /**
     * @param $templateId the email twig template name (without the .html.twig or .txt.twig extension)
     * @param array $campaignParams associative array of campaign parameters and values, that are used in this email to track clicks on links.
     * @param array $emailTemplateParams associative array with the context available when the email template got rendered with the twig engine.
     * @param $messageId the message id of the swift-message
     * @param $to email address(es) of the "to" header.
     * @param $cc email address(es) of the "cc" header.
     * @param $bcc email address(es) of the "bcc" header.
     * @return string the html-code to be inserted before the hml-close tag
     */
    public function getTrackingImgCode($templateBaseId, array $campaignParams, array $emailTemplateParams, $messageId, $to, $cc, $bcc);
}
