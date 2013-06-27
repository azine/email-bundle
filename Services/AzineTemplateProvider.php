<?php
namespace Azine\EmailBundle\Services;

/**
 * This Service provides the templates and template-variables to be used for emails
 * @author Dominik Businger
 */
use Symfony\Bundle\FrameworkBundle\Translation\Translator;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AzineTemplateProvider implements TemplateProviderInterface{

	private $templateImageDir;

	public function __construct(UrlGeneratorInterface $router, Translator $translator, array $parameters){
		$this->router = $router;
		$this->translator = $translator;
		$this->templateImageDir = __DIR__."/".$parameters['template_image_dir'];
	}

	/**
	 * Override this function for your template(s)
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.TemplateProviderInterface::getTemplateFor()
	 */
	public function getTemplateFor($type = 'default'){
		return 'AzineEmailBundle::default';
	}

	/**
	 * Override this function for your template(s)
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.TemplateProviderInterface::addTemplateVariablesFor()
	 */
	public function addTemplateVariablesFor($type = 'default', array $defaultVars){

		// define the absolute path to the directory that contains the
		// images that are used in the layout (e.g. shadow.png, logo.png etc.)
		$defaultVars['imagesDir'] = $this->templateImageDir;


		// add images to be encoded and attached to the email
		$defaultVars['logo_png'] 				= $imagesDir.'logo.png';
		$defaultVars['bottom_shadow_png']		= $imagesDir.'bottomshadow.png';
		$defaultVars['top_shadow_png']			= $imagesDir.'topshadow.png';
		$defaultVars['left_shadow_png']			= $imagesDir.'left-shadow.png';
		$defaultVars['right_shadow_png']		= $imagesDir.'right-shadow.png';
		$defaultVars['placeholder_png'] 		= $imagesDir.'placeholder.png';


		// define some colors to be reused in the folloing style-definitions
		$azGreen								= "green";
		$azBlue									= "blue";
		$blackColor								= "black";
		$lightGray 								= "#EEEEEE";

		// add html-styles for your html-emails
		// css does not work in html-emails, so all styles need to be
		// embeded directly into the html-elements
		$defaultVars["azGreen"] 				= $azGreen;
		$defaultVars["azBlue"] 					= $azBlue;
		$defaultVars["blackColor"]				= $blackColor;
		$defaultVars["lightGray"]				= $lightGray;
		$defaultVars["h2Style"]					= "style='padding:0; margin:0; font:bold 24px Arial; color:$azBlue; text-decoration:none;'";
		$defaultVars["h3Style"]					= "style='margin:12px 0 5px 0; font:bold 18px Arial; padding:0; color:$azGreen; text-decoration:none;'";
		$defaultVars["h4Style"]					= "style='padding:0; margin:0 0 20px 0; color:$blackColor; font-size:14px; text-decoration:none;'";
		$defaultVars["salutationStyle"]			= "style='color:$azBlue; font:bold 16px Arial;'";
		$defaultVars["dateStyle"]				= "style='padding:0; margin:5px 0; color:$blackColor; font-weight:bold; font-size:12px;'";
		$defaultVars["smallerTextStyle"]		= "style='font: normal 13px/18px Arial, Helvetica, sans-serif;'";

		return $defaultVars;
	}

	/**
	 * Override this function for your template(s)
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.TemplateProviderInterface::getTemplateBlocksFor()
	 */
	public function addTemplateBlocksFor($type = 'default', array $templateVariables){
		// the blocks depend on the following images, so they must be present in the $vars-array
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
		$upLinkTitle = $this->translatorService->trans("_az.email.template.go.to.top.link.label");

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
}
