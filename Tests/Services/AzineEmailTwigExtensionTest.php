<?php

namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\Services\AzineEmailTwigExtension;

class AzineEmailTwigExtensionTest extends \PHPUnit_Framework_TestCase
{
    private $longText = "Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.";

    public function testFilters()
    {
        $twigExtension = new AzineEmailTwigExtension();
        $filters = $twigExtension->getFilters();
        $this->assertEquals(2, sizeof($filters), "There should only be one filter.");
        $this->assertTrue(array_key_exists("textWrap", $filters),"The filter textWrap should exist.");
        $this->assertTrue(array_key_exists("urlEncodeText", $filters),"The filter urlEncodeText should exist.");

        $filter = $filters["textWrap"];
        $this->assertTrue($filter instanceof \Twig_Filter_Method, "Twig_Filter_Method expected as filter for textWrap.");

        $this->assertEquals('azine_email_bundle_twig_extension', $twigExtension->getName());
    }

    public function testTextWrap()
    {
        $twigExtension = new AzineEmailTwigExtension();
        $wrapped = $twigExtension->textWrap($this->longText);
        $nlIndex = strpos($wrapped, "\n");
        $this->assertLessThanOrEqual(75, $nlIndex);
        $this->assertGreaterThan(65, $nlIndex);
    }

    public function testTextWrap60()
    {
        $twigExtension = new AzineEmailTwigExtension();
        $wrapped = $twigExtension->textWrap($this->longText, 60);
        $nlIndex = strpos($wrapped, "\n");
        $this->assertLessThanOrEqual(60, $nlIndex);
        $this->assertGreaterThan(55, $nlIndex);
    }

    public function testTextWrap100()
    {
        $twigExtension = new AzineEmailTwigExtension();
        $wrapped = $twigExtension->textWrap($this->longText, 100);
        $nlIndex = strpos($wrapped, "\n");
        $this->assertLessThanOrEqual(100, $nlIndex);
        $this->assertGreaterThan(90, $nlIndex);
    }

    public function testUrlEncodeText()
    {
        $twigExtension = new AzineEmailTwigExtension();

        $percent = "%";
        $amp = "&";
        $backslash = "\\";
        $lineBreak = "
";

        $textWithSpecialChars = "blabla $percent $amp $backslash $lineBreak blabla $percent $amp $backslash $lineBreak ";

        $textUrlEncoded = $twigExtension->urlEncodeText($textWithSpecialChars);

        $this->assertFalse(strpos($textUrlEncoded, $amp));
        $this->assertFalse(strpos($textUrlEncoded, $backslash));
        $this->assertFalse(strpos($textUrlEncoded, $lineBreak));

        $this->assertStringCount("%0D%0A", $textUrlEncoded, 2);
        $this->assertStringCount("%20", $textUrlEncoded, 10);
        $this->assertStringCount("%26", $textUrlEncoded, 2);
        $this->assertStringCount("%5C", $textUrlEncoded, 2);
        $this->assertStringCount("%25", $textUrlEncoded, 2);
        $this->assertStringCount("%", $textUrlEncoded, 20);

    }

    /**
     * @param string  $needle
     * @param integer $expectedCount
     */
    private function assertStringCount($needle, $haystack, $expectedCount)
    {
        $count = 0;
        str_replace($needle, "--", $haystack, $count);
        $this->assertEquals($expectedCount, $count, "Found $needle $count times instead of $expectedCount");
    }
}
