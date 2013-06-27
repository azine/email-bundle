<?php

namespace Azine\EmailBundle\Services;

/**
 * Interface with methods required by the AzineEmailBundle to render the email-content in nice templates
 * Azine\EmailBundle\Entity\TemplateProviderInterface
 */
interface TemplateProviderInterface {

	const DEFAULT_TYPE = 'default';
	/**
	 * @param string $type the type of email to render
	 * @return string the template id in standard-notation, but without the ".format.twig" at the end => "default.html.twig"/"default.txt.twig" should be returned as "default"
	 */
	public function getTemplateFor($type = 'default');

	/**
	 * Add all variables that are required to render the layout of the html-email-template
	 * @param string $type the type of email to render
	 * @param array $templateVariables array with variables required to render the content in the email
	 * @return array of template-vars
	 */
	public function addTemplateVariablesFor($type = 'default', array $templateVariables);


	/**
	 * Add template blocks that refer to images encoded in the email to the supplied array.
	 * This function must be called after the images have been embeded.
	 * @param array $templateVariables
	 * @param string $type
	 */
	public function addTemplateBlocksFor($type = 'default', array $templateVariables);

}