<?php
namespace Azine\EmailBundle\Services;

/**
 * This Service provides the templates and template-variables to be used for emails
 * @author Dominik Businger
 */
use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Symfony\Bundle\FrameworkBundle\Translation\Translator;

use Symfony\Component\Routing\Generator\UrlGenerator;

class AzineTemplateProvider implements TemplateProviderInterface {

	/**
	 * Override this function for your template(s)!
	 *
	 * @param $type type of email e.g. TemplateProviderInterface::NEWSLETTER_TYPE
	 * @return template-id $template for this type
	 */
	protected function fillTemplatesArrayFor($type){
		// this implementation uses the same template for all types.
		// override this function with a more sophiticated logic
		// if you need different templates for different email-types

		// use the default template for all types
		$this->templateArray[$type] = 'AzineEmailBundle::baseEmailLayout';
	}

	/**
	 * Override this function for your template(s)!
	 *
	 * For each template you like to render, you need to supply the array with variables that can be passed to the twig renderer.
	 * Those variables can then be used in the twig-template => {{ logo_png }}
	 *
	 * In this function you should fill a set of variables for eacht template.
	 *
	 * @param string $template
	 */
	protected function fillParamArrayFor($template){
		// this implementation uses the same array for all templates.
		// override this function with a more sophiticated logic
		// if you need different styles for different templates.

		$newVars = array();

		// add template-specific stuff.
		if($template == $this->getTemplateFor(self::NOTIFICATION_TYPE)){
			$newVars['subject'] = "Your notifications sent by AzineEmailBundle";
		}

		if($template == $this->getTemplateFor(self::NEWSLETTER_TYPE)){
			$newVars['subject'] = "Newsletter sent by AzineEmailBundle";
		}

		// add generic stuff needed for all templates


		// add images to be encoded and attached to the email
		// the absolute file-path will be replaced with the CID of
		// the attached image, so it will be rendered in the html-email.
		// => to render the logo inside your twig-template you can write:
		// <html><body> ... <img src="{{ logo_png }}" alt="logo"> ... </body></html>
		$imagesDir = $this->getTemplateImageDir();
		$newVars['logo_png'] 				= $imagesDir.'logo.png';
		$newVars['bottom_shadow_png']		= $imagesDir.'bottomshadow.png';
		$newVars['top_shadow_png']			= $imagesDir.'topshadow.png';
		$newVars['left_shadow_png']			= $imagesDir.'left-shadow.png';
		$newVars['right_shadow_png']		= $imagesDir.'right-shadow.png';
		$newVars['placeholder_png'] 		= $imagesDir.'placeholder.png';

		// define some colors to be reused in the following style-definitions
		$azGreen							= "green";
		$azBlue								= "blue";
		$blackColor							= "black";
		$lightGray 							= "#EEEEEE";
		$newVars["azGreen"] 				= $azGreen;
		$newVars["azBlue"] 					= $azBlue;
		$newVars["blackColor"]				= $blackColor;
		$newVars["lightGray"]				= $lightGray;

		// add html-styles for your html-emails
		// css does not work in html-emails, so all styles need to be
		// embeded directly into the html-elements
		$newVars["h2Style"]					= "style='padding:0; margin:0; font:bold 24px Arial; color:$azBlue; text-decoration:none;'";
		$newVars["h3Style"]					= "style='margin:12px 0 5px 0; font:bold 18px Arial; padding:0; color:$azGreen; text-decoration:none;'";
		$newVars["h4Style"]					= "style='padding:0; margin:0 0 20px 0; color:$blackColor; font-size:14px; text-decoration:none;'";
		$newVars["salutationStyle"]			= "style='color:$azBlue; font:bold 16px Arial;'";
		$newVars["dateStyle"]				= "style='padding:0; margin:5px 0; color:$blackColor; font-weight:bold; font-size:12px;'";
		$newVars["smallerTextStyle"]		= "style='font: normal 13px/18px Arial, Helvetica, sans-serif;'";

		$this->paramArrays[$template] = $newVars;
	}

	/**
	 * Override this function for your template(s) if you use other "snippets" with embeded images.
	 *
	 * This function adds more complex elements to the array of variables that are passed
	 * to the twig-renderer, just before sending the mail.
	 *
	 * In this implementation for example some reusable "snippets" are added to render
	 * a nice shadow arround content parts and add a "link to top" at the top of each part.
	 *
	 * As these "snippets" contain references to images that first had to be embeded into the
	 * Message, these "snippets" are added after embedding/adding the attachments.
	 *
	 * This means, that here the variable "bottom_shadow_png" defined in AzineTemplateProvider.fillParamArrayFor()
	 * does not contain the path to the image-file anymore but now contains the CID of the embeded image.
	 *
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.TemplateProviderInterface::getTemplateBlocksFor()
	 */
	public function addTemplateSnippetsWithEmbededImagesFor($template, array $vars, $emailLocale){
		// this implementation uses the same array for all templates.
		// override this function with a more sophiticated logic
		// if you need different styles for different templates.


		// the snippets added in this implementation depend on the
		// following images, so they must be present in the $vars-array
		if(
				!array_key_exists("bottom_shadow_png", $vars) ||
				!array_key_exists("top_shadow_png", $vars) ||
				!array_key_exists("left_shadow_png", $vars) ||
				!array_key_exists("right_shadow_png", $vars)
		){
			throw new \Exception("some required images are not yet added to the template-vars array.");
		}

		// define some vars that are used several times
		$lightGray = $vars["lightGray"];;
		$blackColor = $vars["blackColor"];
		$upLinkTitle = $this->getTranslator($emailLocale)->trans("_az.email.template.go.to.top.link.label", array(), 'messages', $emailLocale);

		// create and add html-elements for easy reuse in the twig-templates
		$vars["linkToTop"] 		= "<a href='#top' style='text-decoration:none;color:$blackColor' title='$upLinkTitle'>Λ</a>";
		$vars["tableOpen"]		= "<table width='640' border='0' align='center' cellpadding='0' cellspacing='0'  style='font: normal 14px/18px Arial, Helvetica, sans-serif;'>";
		$vars["topShadow"]		= $vars["tableOpen"]."<tr><td colspan='3' width='640'><img src='".$vars["top_shadow_png"]."' alt='' style='vertical-align: bottom;'/></td></tr>";
		$vars["leftShadow"]		= "<tr><td width='10' style='border-right: 1px solid $lightGray; background-image: url(\"".$vars["left_shadow_png"]."\");'>&nbsp;</td>";
		$vars["rightShadow"]	= "<td width='10' style='border-left: 1px solid $lightGray; background-image: url(\"".$vars["right_shadow_png"]."\");'>&nbsp;</td></tr>";
		$vars["bottomShadow"]	= "	<tr><td colspan='3' width='640'><img src='".$vars["bottom_shadow_png"]."' alt='' style='vertical-align: top;'/></td></tr></table>";
		$vars["linkToTopRow"]	= $vars["leftShadow"]."<td width='610' bgcolor='white' style='text-align: right; padding: 5px 5px 0; border-top: 1px solid $lightGray;'>".$vars["linkToTop"]."</td>".$vars["rightShadow"];
		$vars["cellSeparator"]	= "</td>".$vars["rightShadow"].$vars["bottomShadow"].$vars["topShadow"].$vars["linkToTopRow"].$vars["leftShadow"]."<td bgcolor='white' width='580' align='left' valign='top' style='padding:10px 20px 20px 20px;'>";

		return $vars;
	}

//////////////////////////////////////////////////////////////////////////
/* You probably don't need to change or override any of the stuff below */
//////////////////////////////////////////////////////////////////////////

	/**
	 * Full filesystem-path to the directory where you store your email-template images.
	 * @var string
	 */
	private $templateImageDir;

	/**
	 * @var UrlGeneratorInterface
	 */
	private $router;

	/**
	 * @var Translator
	 */
	private $translator;

	/**
	 * Array to store all the arrays for the different templates.
	 * @var array of array
	 */
	protected $paramArrays = array();

	/**
	 * Array with templateIds for all types of emails in your application.
	 * @var string
	 */
	protected $templateArray = array();

	public function __construct(UrlGeneratorInterface $router, Translator $translator, array $parameters){
		$this->router = $router;
		$this->translator = $translator;
		$this->templateImageDir = $parameters[AzineEmailExtension::TEMPLATE_IMAGE_DIR];
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.TemplateProviderInterface::getTemplateImageDir()
	 */
	public function getTemplateImageDir(){
		return $this->templateImageDir;
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.TemplateProviderInterface::addTemplateVariablesFor()
	 */
	public function addTemplateVariablesFor($template, array $contentVariables){
		if(!array_key_exists($template, $this->paramArrays)){
			$this->fillParamArrayFor($template);
		}
		return array_merge($this->paramArrays[$template], $contentVariables);
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.TemplateProviderInterface::getTemplateFor()
	 */
	public function getTemplateFor($type){
		if(!array_key_exists($type, $this->templateArray)){
			$this->fillTemplatesArrayFor($type);
		}
		return $this->templateArray[$type];
	}

	/**
	 * @return UrlGeneratorInterface
	 */
	protected function getRouter(){
		return $this->router;
	}

	/**
	 * Only use the translator here when you already know in which language the user should get the email.
	 * @param $emailLocale
	 * @return Translator
	 */
	protected function getTranslator($emailLocale){
		if($emailLocale == null){
			throw new \Exception("Only use the translator here when you already know in which language the user should get the email.");
		}
		return $this->translator;
	}


}
