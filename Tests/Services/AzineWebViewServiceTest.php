<?php
namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\Services\AzineWebViewService;

class AzineWebViewServiceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return \PHPUnit_Framework_MockObject_MockObject mock of the UrlGeneratorInterface
     */
    private function getMockRouter()
    {
        return $this->getMockBuilder("Symfony\Component\Routing\Generator\UrlGeneratorInterface")->disableOriginalConstructor()->getMock();
    }

    public function testGetTemplatesForWebView()
    {
        $routerMock = $this->getMockRouter();
        $webViewService = new AzineWebViewService($routerMock);

        $this->assertTrue(is_array($webViewService->getTemplatesForWebPreView()));
    }

    public function testGetTestMailAccounts()
    {
        $routerMock = $this->getMockRouter();
        $webViewService = new AzineWebViewService($routerMock);

        $this->assertTrue(is_array($webViewService->getTestMailAccounts()));
    }

    public function testGetDummyVarsFor()
    {
        $routerMock = $this->getMockRouter();
        $webViewService = new AzineWebViewService($routerMock);

        $this->assertTrue(is_array($webViewService->getDummyVarsFor("some template", "de")));
    }

    public function testAddTestMailAccount()
    {
        $routerMock = $this->getMockRouter();
        $webViewService = new AzineWebViewService($routerMock);

        $description = "Some description";
        $emailAddress = "sfsdf@mail.com";
        $args = array(array(), $description, $emailAddress);
        $returnValue = self::getMethod('addTestMailAccount')->invokeArgs($webViewService, $args);

        $this->assertEquals(array('accountDescription' => $description, 'accountEmail' => $emailAddress ), $returnValue[0]);
    }

    public function testAddTemplate()
    {
        $templateId = "someId";
        $description = "some new template";
        $formats = array('txt','html', 'xml');
        $someUrl = "/some/url/to/the/preview";

        $routerMock = $this->getMockRouter();
        $routerMock->expects($this->once())->method("generate")->with("azine_email_web_preview", array('template' => $templateId))->will($this->returnValue($someUrl));

        $webViewService = new AzineWebViewService($routerMock);

        $args = array( array(), $description, $templateId, $formats);
        $templates = self::getMethod('addTemplate')->invokeArgs($webViewService, $args);

        $this->assertEquals(1, sizeof($templates));
        $this->assertEquals(array(	'url' => $someUrl,
                                    'description'	=> $description,
                                    'formats' 		=> $formats,
                                    'templateId'	=> $templateId,
                                    ), $templates[0]);

    }

    /**
     * @param string $name
     */
    private static function getMethod($name)
    {
        $class = new \ReflectionClass("Azine\EmailBundle\Services\AzineWebViewService");
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}
