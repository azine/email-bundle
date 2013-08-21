<?php
namespace Azine\EmailBundle\Services;

use Symfony\Component\HttpKernel\KernelInterface;

class AzineEmailTwigExtension extends \Twig_Extension
{
	public function __construct()
	{
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilters()	{
		return array(
			'textWrap' => new \Twig_Filter_Method($this, 'textWrap'),
		);
	}

	/**
	 * Wrap the text to the lineLength is not exeeded.
	 * @param string $text
	 * @param integer $lineLength default: 75
	 * @return string the wrapped string
	 */
	public function textWrap($text, $lineLength = 75){
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
}