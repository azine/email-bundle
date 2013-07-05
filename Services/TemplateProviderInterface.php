<?php
namespace Azine\EmailBundle\Services;

/**
 * Interface with methods required by the AzineEmailBundle to render the email-content in nice templates
 * Azine\EmailBundle\Entity\TemplateProviderInterface
 */
interface TemplateProviderInterface {

	const NEWSLETTER_TYPE = 'newsletter';
	const NOTIFICATION_TYPE = 'notification';

	/**
	 * Get the twig template that should be used for the email.
	 * The returned twig-template must contain the blocks "subject" "body_text" and "body_html"
	 *
	 * @param string $type the type of email to render. Either TemplateProviderInterface::NEWSLETTER_TYPE, TemplateProviderInterface::NOTIFICATION_TYPE or any type from your own TemplateProviderInterface implementation
	 * @return string the template id in standard-notation, without the ending ( .txt.twig) => "AcmeFooBundle:bar:default"
	 */
	public function getTemplateFor($type);

	/**
	 * Add all styles and variables that are required to render the layout of the html-email-template
	 *
	 * @param string $template the twig template for the email to render
	 * @param array $contentVariables array with variables required to render the content in the email
	 * @return array of merged template- and content-vars. Variables in the supplied array will NOT be replaced by newly added ones.
	 */
	public function addTemplateVariablesFor($template, array $contentVariables);


	/**
	 * Add template blocks that refer to images encoded in the email to the supplied array.
	 * This function will be called AFTER the images have been embeded, so you can define vars that include embede images => e.g. see variable "cellSeparator" in class AzineTemplateProvider.
	 * @param string $template the twig template for the email to render
	 * @param array $vars
	 * @param string $emailLocale the locale to be used for translations for this single email
	 * @return array of merged template-vars. Variables in the supplied array WILL BE replaced by newly added ones, if the use the same key.
	 */
	public function addTemplateSnippetsWithEmbededImagesFor($template, array $vars, $emailLocale);

	/**
	 * Get the absolute filesystem path to the folder where  the template-images are stored.
	 */
	public function getTemplateImageDir();


}