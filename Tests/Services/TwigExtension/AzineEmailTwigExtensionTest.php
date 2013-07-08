<?php

namespace Azine\EmailBundle\Tests\Services\TwigExtension;

use Azine\EmailBundle\Services\AzineEmailTwigExtension;

class AzineEmailTwigExtensionTest extends \PHPUnit_Framework_TestCase {

	private $longText = "Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.";

	public function testFilters(){
		$twigExtension = new AzineEmailTwigExtension();
		$filters = $twigExtension->getFilters();
		$this->assertEquals(1, sizeof($filters), "There should only be one filter.");
		$this->assertEquals("textWrap", key($filters), "The filter should be called textWrap.");

		$filter = $filters["textWrap"];
		$this->assertTrue($filter instanceof \Twig_Filter_Method, "Twig_Filter_Method expected as filter for textWrap.");
	}

	public function testTextWrap(){
		$twigExtension = new AzineEmailTwigExtension();
		$wrapped = $twigExtension->textWrap($this->longText);
		$nlIndex = strpos($wrapped, "\n");
		$this->assertLessThanOrEqual(75, $nlIndex);
		$this->assertGreaterThan(65, $nlIndex);
	}

	public function testTextWrap60(){
		$twigExtension = new AzineEmailTwigExtension();
		$wrapped = $twigExtension->textWrap($this->longText, 60);
		$nlIndex = strpos($wrapped, "\n");
		$this->assertLessThanOrEqual(60, $nlIndex);
		$this->assertGreaterThan(55, $nlIndex);
	}

	public function testTextWrap100(){
		$twigExtension = new AzineEmailTwigExtension();
		$wrapped = $twigExtension->textWrap($this->longText, 100);
		$nlIndex = strpos($wrapped, "\n");
		$this->assertLessThanOrEqual(100, $nlIndex);
		$this->assertGreaterThan(90, $nlIndex);
	}
}
