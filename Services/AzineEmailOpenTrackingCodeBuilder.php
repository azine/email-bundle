<?php
namespace Azine\EmailBundle\Services;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Ramsey\Uuid\Uuid;

/**
 * Implementation of the EmailOpenTrackingCodeBuilderInterface used to track email open events.
 *
 * This implementation will create a html snippet with the image tracker code for
 * either GoogleAnalytics or Piwik, depending on the configured tracking url
 * ( azine_email_email_open_tracking_url) in your config.yml
 *
 * Class AzineEmailOpenTrackingCodeBuilder
 * @package Azine\EmailBundle\Services
 */
class AzineEmailOpenTrackingCodeBuilder implements EmailOpenTrackingCodeBuilderInterface {

    /**
     * @var  string
     */
    protected $tracking_params_campaign_source;

    /**
     * @var  string
     */
    protected $tracking_params_campaign_medium;

    /**
     * @var  string
     */
    protected $tracking_params_campaign_content;

    /**
     * @var  string
     */
    protected $tracking_params_campaign_name;

    /**
     * @var  string
     */
    protected $tracking_params_campaign_term;

    /**
     * @var $string|null
     */
    private $trackingUrlTemplate;

    /**
     * @var string the html-code template
     */
    protected $imageHtmlCode = "<img src='%s' style='border:0' alt='' />";

    /**
     * @param string $trackingUrlTemplate the url configured in your config.yml or null if you didn't specify a tracking url.
     * @param array $parameters array with the parameter names for the campaign tracking
     */
    public function __construct($trackingUrlTemplate, $parameters){
        $this->trackingUrlTemplate = $trackingUrlTemplate;
        $this->tracking_params_campaign_content = $parameters[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT];
        $this->tracking_params_campaign_medium = $parameters[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM];
        $this->tracking_params_campaign_name = $parameters[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME];
        $this->tracking_params_campaign_source = $parameters[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE];
        $this->tracking_params_campaign_term = $parameters[AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM];
    }

    /**
     * @param string $templateBaseId the template used for rendering the email (without the .html.twig or .txt.twig extension)
     * @param array $campaignParams the campaign-parameters used for this email
     * @param string $messageId the id of the message
     * @param string $to to-recipient-email(s) or null
     * @param string $cc cc-recipient-email(s) or null
     * @param string $bcc bcc-recipient-email(s) or null
     *
     * @return null|string Email open tracking code for google analytics or piwik or null
     */
    public function getTrackingImgCode($templateBaseId, array $campaignParams, array $emailTemplateParams, $messageId, $to, $cc, $bcc){
        if($this->trackingUrlTemplate === null){
            return null;
        }

        $recipients = md5($this->merge($to, $cc, $bcc));

        if(strpos($this->trackingUrlTemplate, 'www.google-analytics.com') !== false) {
            $trackingUrl = $this->getGoogleAnalyticsUrl($this->trackingUrlTemplate, $templateBaseId, $campaignParams, $emailTemplateParams, $messageId, $recipients);
        } else {
            $trackingUrl = $this->getPiwikUrl($this->trackingUrlTemplate, $templateBaseId, $campaignParams, $emailTemplateParams, $messageId, $recipients);
        }

        // return the html code for the tracking image
        $imgTrackingCode = sprintf($this->imageHtmlCode, $trackingUrl);
        return $imgTrackingCode;
    }

    /**
     * concatenate all recipients into an array and implode with ';' to a string
     * @param string|array $to
     * @param string|array $cc
     * @param string|array $bcc
     * @return string
     */
    protected function merge($to, $cc, $bcc){
        if($to && !is_array($to)){
            $to = array($to);
        }
        if(!is_array($cc)){
            $cc = array($cc);
        }
        if(!is_array($bcc)){
            $bcc = array($bcc);
        }
        $all = array_merge($to, $cc, $bcc);

        return implode(";", $all);
    }

    /**
     * Build tracking image code with an URL according to these sources:
     * http://dyn.com/blog/tracking-email-opens-via-google-analytics/
     * https://developers.google.com/analytics/devguides/collection/protocol/v1/email#protocol
     *
     * @param string $baseUrl string something like: https://www.google-analytics.com/collect?v=1&cm=email&t=event&ec=email&ea=open&tid=TRACKING_ID replace the TRACKING_ID with your google analytics tracking ID.
     * @param string $templateBaseId
     * @param array $campaignParams
     * @param array $emailTemplateParams
     * @param string $messageId
     * @param string|array $recipients
     * @return string
     */
    protected function getGoogleAnalyticsUrl($baseUrl, $templateBaseId, array $campaignParams, array $emailTemplateParams, $messageId, $recipients){
        $url = $baseUrl.
            "&uid=".$recipients.                              // anonymized id generated from the concatenated string of all recipients email addresses
            "&cid=".Uuid::uuid4()->toString().                // random UUID
            "&el=".$recipients.                               // anonymized id generated from the concatenated string of all recipients email addresses
            "&dp=/email/".$templateBaseId.                    // the email-template used for this email
            "&cs=".$this->getCampaignSource($campaignParams, $templateBaseId). // campaing source for this email
            "&cn=".$this->getCampaignName($campaignParams).   // campaign name for this email
            "&z=".microtime();                                // cache buster

        return $url;
    }

    /**
     * Build tracking image code with an URL according to these sources:
     *
     *
     * @param string $baseUrl string something like: https://your.host.com/piwik-directory/piwik.php?&rec=1&bots=1&e_c=email&e_a=open&e_v=1&idsite=SITE_ID replace the path to your piwik.php and the SITE_ID according to your needs.
     * @param string $templateBaseId string
     * @param array $campaignParams
     * @param array $emailTemplateParams
     * @param string $messageId
     * @param string $recipients
     * @return string
     */
    protected function getPiwikUrl($baseUrl, $templateBaseId, array $campaignParams, array $emailTemplateParams, $messageId, $recipients){
        $url = $baseUrl.
            "&_id=".substr($recipients, 0, 16).                     // user: 16 characters hexadecimal string
            "&url=/email/".$templateBaseId.                         // document path
            "&rand=".microtime().                                   // cache buster
            "&e_n=".$this->getCampaignSource($campaignParams, $templateBaseId).      // event name => will be categorized with the / => e.g. email / newsletter
            "&action_name=".$this->getCampaignName($campaignParams);// action name

        return $url;
    }

    /**
     * @param array $campaignParams
     * @param string $templateId
     * @return string if no source-value is defined in the $campaignParams, $templateId will be used.
     */
    protected function getCampaignSource($campaignParams, $templateId){
        if(array_key_exists($this->tracking_params_campaign_source, $campaignParams)){
            return $campaignParams[$this->tracking_params_campaign_source];
        }
        return $templateId;
    }

    /**
     * @param array $campaignParams
     * @return string if no name-value is defined in the $campaignParams, date("y-m-d") will be used.
     */
    protected function getCampaignName($campaignParams){
        if(array_key_exists($this->tracking_params_campaign_name, $campaignParams)){
            return $campaignParams[$this->tracking_params_campaign_name];
        }
        return date("y-m-d");
    }
}
