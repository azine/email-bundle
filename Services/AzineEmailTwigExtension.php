<?php
namespace Azine\EmailBundle\Services;

use Symfony\Component\Translation\TranslatorInterface;

class AzineEmailTwigExtension extends \Twig_Extension
{
    /**
     * @var TemplateProviderInterface
     */
    private $templateProvider;

    /**
     * @var TranslatorInterface		
     */		
    private $translator;
    
    /**
     * @var array
     */
    private $domainsToTrack;

    /**
     * @param TemplateProviderInterface $templateProvider
     * @param array of string $domainsToTrack
     */
    public function __construct(TemplateProviderInterface $templateProvider, TranslatorInterface $translator, array $domainsToTrack = array()){
        $this->templateProvider = $templateProvider;
        $this->translator = $translator;
        $this->domainsToTrack = $domainsToTrack;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        $filters[] = new \Twig_SimpleFilter('textWrap', array($this, 'textWrap'));
        $filters[] = new \Twig_SimpleFilter('urlEncodeText', array($this, 'urlEncodeText'), array('is_safe' => array('html')));
        $filters[] = new \Twig_SimpleFilter('addCampaignParamsForTemplate', array($this, 'addCampaignParamsForTemplate'), array('is_safe' => array('html')));
        $filters[] = new \Twig_SimpleFilter('stripAndConvertTags', array($this, 'stripAndConvertTags'), array('is_safe' => array('html')));
        return $filters;
    }

    public function urlEncodeText($text)
    {
        $text = str_replace("%","%25", $text);
        $text = str_replace(array(	"\n",
                                    " ",
                                    "&",
                                    "\\",
                                    "<",
                                    ">",
                                    '"',
                                    "	",
                                ),
                            array(	"%0D%0A",
                                    "%20",
                                    "%26",
                                    "%5C",
                                    "%3D",
                                    "%3E",
                                    "%23",
                                    "%09",
                                ), $text);

        return $text;
    }

    /**
     * Wrap the text to the lineLength is not exeeded.
     * @param  string  $text
     * @param  integer $lineLength default: 75
     * @return string  the wrapped string
     */
    public function textWrap($text, $lineLength = 75)
    {
        return wordwrap($text, $lineLength);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'azine_email_bundle_twig_extension';
    }

    public function addCampaignParamsForTemplate($html, $templateId, $templateParams){
        $campaignParams = $this->templateProvider->getCampaignParamsFor($templateId, $templateParams);
        return $this->addCampaignParamsToAllUrls($html, $campaignParams);
    }

    /**
     * Add the campaign-parameters to all URLs in the html
     * @param  string $html
     * @param  array  $campaignParams
     * @return string
     */
    public function addCampaignParamsToAllUrls($html, $campaignParams)
    {

        $urlPattern = '/(href=[\'|"])(http[s]?\:\/\/\S*)([\'|"])/';

        $filteredHtml = preg_replace_callback($urlPattern, function ($matches) use ($campaignParams) {
                                                                    $start = $matches[1];
                                                                    $url = $matches[2];
                                                                    $end = $matches[3];
                                                                    $domain = parse_url($url, PHP_URL_HOST);

                                                                    // if the url is not in the list of domains to track then
                                                                    if(array_search($domain, $this->domainsToTrack) === false){
                                                                        // don't append tracking parameters to the url
                                                                        return $start.$url.$end;
                                                                    }

                                                                    // avoid duplicate params and don't replace existing params
                                                                    $params = array();
                                                                    foreach($campaignParams as $nextKey => $nextValue){
                                                                        if(strpos($url, $nextKey) === false){
                                                                            $params[$nextKey] = $nextValue;
                                                                        }
                                                                    }

                                                                    $urlParams = http_build_query($params);

                                                                    if (strpos($url,"?") === false) {
                                                                        $urlParams = "?".$urlParams;
                                                                    } else {
                                                                        $urlParams = "&".$urlParams;
                                                                    }

                                                                    $replacement = $start.$url.$urlParams.$end;

                                                                    return $replacement;

                                                                }, $html);

        return $filteredHtml;
    }

    /**
     * Convert:
     * - a-tags to show the link and if the link-text is not contained in the link, also the link-text
     * - remove double-whitespaces and whitespaces at line beginnings and ends.
     * - html-special chars to their original representation (php => htmlspecialchars_decode)
     * and then remove all html-tags (php => strip_tags)
     */
    public function stripAndConvertTags($html){

        $linkConvertedHtml = preg_replace_callback('/<a.*?href=[\'|"](.+?)[\'|"].*?>(.*?)<\/a>/s', function ($matches) {
            $url = $matches[1];
            $text = trim(strip_tags($matches[2]));

            if(strlen($text) == 0 || stripos($url, $text) !== false){
                $replacement = $url;
            } else {
                $replacement = $text . ": " . $url;
            }

            return $replacement;

        }, $html);

        $txt = strip_tags($linkConvertedHtml);
        $txt = preg_replace('/[[:blank:]]+/', ' ', $txt);
        $txt = preg_replace("/\n[[:blank:]]/", "\n", $txt);
        $txt = preg_replace("/[[:blank:]]\n/", "\n", $txt);
        $txt = html_entity_decode($txt);
        return $txt;
    }
}
