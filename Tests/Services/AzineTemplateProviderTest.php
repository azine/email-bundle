<?php
namespace Azine\EmailBundle\Tests\Services;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\WebTestCase;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;

use Azine\EmailBundle\Services\AzineTemplateProvider;

use FOS\UserBundle\Security\EmailUserProvider;

class AzineTemplateProviderTest extends \PHPUnit_Framework_TestCase {

	private function getMockSetup() {
		$translatorMock = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->setMethods(array('trans'))->getMock();

		$translatorMock->expects($this->any())->method("trans")->will($this->returnValueMap(array(
														array('html.email.go.to.top.link.label', array(), 'messages', "de", "de übersetzung"),
														array('html.email.go.to.top.link.label', array(), 'messages', "en", "en translation"),
												)));

		$routerMock = $this->getMockBuilder("Symfony\Component\Routing\Generator\UrlGeneratorInterface")->disableOriginalConstructor()->getMock();
		$routerMock->expects($this->any())->method('generate')->withAnyParameters()->will($this->returnCallback(array($this,'createRelativeUrl')));

		$params = array(AzineEmailExtension::TEMPLATE_IMAGE_DIR => realpath(__DIR__."/../../Resources/htmlTemplateImages/"), AzineEmailExtension::ALLOWED_IMAGES_FOLDERS => array(realpath(__DIR__."/../../Resources/htmlTemplateImages/")));

		return array('router' => $routerMock, "translator" => $translatorMock, 'params' => $params);
	}

	public function createRelativeUrl($routeName, $params){
		if($routeName == "azine_email_serve_template_image"){
			return "/template/images/".$params["filename"];
		}
		echo $routeName;
		return "/some/relative/url/to/images/folder";
	}

	public function testAddTemplateVariablesFor() {
		$mocks = $this->getMockSetup();
		$templateProvider = new AzineTemplateProvider($mocks['router'], $mocks['translator'], $mocks['params']);

		// test without contentItems
		$contentVars = array('testVar' => 'testValue');
		$filledVars = $templateProvider->addTemplateVariablesFor(AzineTemplateProvider::FOS_USER_PWD_RESETTING_TEMPLATE, $contentVars);
		$this->assertEquals('testValue', $filledVars['testVar']);
		$this->assertGreaterThan(sizeof($contentVars), sizeof($filledVars));

		$filledVars = $templateProvider->addTemplateVariablesFor(AzineTemplateProvider::FOS_USER_REGISTRATION_TEMPLATE, $contentVars);
		$this->assertEquals('testValue', $filledVars['testVar']);
		$this->assertGreaterThan(sizeof($contentVars), sizeof($filledVars));

		// test with contentItems
		$contentVars[AzineTemplateProvider::CONTENT_ITEMS] = array(array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('otherTestVar' => 'otherTestValue')));
		$filledVars = $templateProvider->addTemplateVariablesFor(AzineTemplateProvider::BASE_TEMPLATE, $contentVars);
		$this->assertEquals('testValue', $filledVars['testVar']);
		$this->assertGreaterThan(sizeof($contentVars), sizeof($filledVars));
		$this->assertTrue(is_array($filledVars[AzineTemplateProvider::CONTENT_ITEMS]));
		$this->assertTrue(is_array($filledVars[AzineTemplateProvider::CONTENT_ITEMS][0][AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE]));
		$this->assertEquals('otherTestValue', $filledVars[AzineTemplateProvider::CONTENT_ITEMS][0][AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE]['otherTestVar']);
	}

	public function testAddSnippetsWithImagesFor() {
		$mocks = $this->getMockSetup();
		$templateProvider = new AzineTemplateProvider($mocks['router'], $mocks['translator'], $mocks['params']);

		$contentVars = array('testVar' => 'testValue');
		$contentVars[AzineTemplateProvider::CONTENT_ITEMS] = array(array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('otherTestVar' => 'otherTestValue')));
		$contentVars = $templateProvider->addTemplateVariablesFor(AzineTemplateProvider::BASE_TEMPLATE, $contentVars);

		$filledVars = $templateProvider->addTemplateSnippetsWithImagesFor(AzineTemplateProvider::BASE_TEMPLATE, $contentVars, "en");
		$this->assertEquals('testValue', $filledVars['testVar']);
		$this->assertTrue(array_key_exists('linkToTop', $filledVars));

		$contentVars2 = array('testVar' => 'testValue');
		$contentVars2[AzineTemplateProvider::CONTENT_ITEMS] = array(array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('otherTestVar' => 'otherTestValue')));
		$contentVars2 = $templateProvider->addTemplateVariablesFor(AzineTemplateProvider::NEWSLETTER_TEMPLATE, $contentVars2);

		$filledVars2 = $templateProvider->addTemplateSnippetsWithImagesFor(AzineTemplateProvider::NEWSLETTER_TEMPLATE, $contentVars2, "en");
		$this->assertEquals($filledVars['linkToTop'], $filledVars2['linkToTop']);

		$contentVars3 = array('testVar' => 'testValue');
		$contentVars3[AzineTemplateProvider::CONTENT_ITEMS] = array(array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('otherTestVar' => 'otherTestValue')));
		$contentVars3 = $templateProvider->addTemplateVariablesFor(AzineTemplateProvider::NOTIFICATIONS_TEMPLATE, $contentVars3);

		$filledVars3 = $templateProvider->addTemplateSnippetsWithImagesFor(AzineTemplateProvider::NOTIFICATIONS_TEMPLATE, $contentVars3, "de");
		$this->assertTrue(array_key_exists('linkToTop', $filledVars3));
		$this->assertNotEquals($filledVars['linkToTop'], $filledVars3['linkToTop']);

		$filledVars4 = $templateProvider->addTemplateSnippetsWithImagesFor(AzineTemplateProvider::BASE_TEMPLATE, $contentVars, "de", true);
		$this->assertTrue(array_key_exists('linkToTop', $filledVars4));
		$this->assertEquals($filledVars3['linkToTop'], $filledVars4['linkToTop']);

	}

	/**
	 * \Exception("some required images are not yet added to the template-vars array.")
	 * @expectedException \Exception
	 */
	public function testAddSnippetsWithImagesForEmptyVars() {
		$mocks = $this->getMockSetup();
		$templateProvider = new AzineTemplateProvider($mocks['router'], $mocks['translator'], $mocks['params']);

		$contentVars = array('testVar' => 'testValue');
		$contentVars[AzineTemplateProvider::CONTENT_ITEMS] = array(	array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('otherTestVar' => 'otherTestValue')));
		$filledVars = $templateProvider->addTemplateSnippetsWithImagesFor(AzineTemplateProvider::BASE_TEMPLATE, $contentVars, 'en');
	}

	/**
	 * \Exception("Only use the translator here when you already know in which language the user should get the email.")
	 * @expectedException \Exception
	 */
	public function testAddSnippetsWithImagesForNoLocale() {
		$mocks = $this->getMockSetup();
		$templateProvider = new AzineTemplateProvider($mocks['router'], $mocks['translator'], $mocks['params']);

		$contentVars = array('testVar' => 'testValue');
		$contentVars[AzineTemplateProvider::CONTENT_ITEMS] = array(	array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('otherTestVar' => 'otherTestValue')));
		$contentVars = $templateProvider->addTemplateVariablesFor(AzineTemplateProvider::BASE_TEMPLATE, $contentVars);
		$filledVars = $templateProvider->addTemplateSnippetsWithImagesFor(AzineTemplateProvider::BASE_TEMPLATE, $contentVars, null);
	}

	public function testGetCampaignParamsFor() {
		$mocks = $this->getMockSetup();
		$templateProvider = new AzineTemplateProvider($mocks['router'], $mocks['translator'], $mocks['params']);

		$campaignParams1 = $templateProvider->getCampaignParamsFor(AzineTemplateProvider::NEWSLETTER_TEMPLATE);
		$this->assertEquals(2, sizeof($campaignParams1));
		$this->assertEquals("newsletter", $campaignParams1['channel']);

		$campaignParams2 = $templateProvider->getCampaignParamsFor(AzineTemplateProvider::NOTIFICATIONS_TEMPLATE);
		$this->assertEquals(2, sizeof($campaignParams2));
		$this->assertEquals("mailnotify", $campaignParams2['channel']);

		$campaignParams3 = $templateProvider->getCampaignParamsFor(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE);
		$this->assertTrue(is_array($campaignParams3));
		$this->assertEquals(0, sizeof($campaignParams3));
	}

	public function testGetTemplateFor() {
		$mocks = $this->getMockSetup();
		$templateProvider = new AzineTemplateProvider($mocks['router'], $mocks['translator'], $mocks['params']);

		$this->assertEquals(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE, $templateProvider->getTemplateFor(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TYPE));
		$this->assertEquals(AzineTemplateProvider::NEWSLETTER_TEMPLATE, $templateProvider->getTemplateFor(AzineTemplateProvider::NEWSLETTER_TYPE));
		$this->assertEquals(AzineTemplateProvider::NOTIFICATIONS_TEMPLATE, $templateProvider->getTemplateFor(AzineTemplateProvider::NOTIFICATIONS_TYPE));
		$this->assertEquals(AzineTemplateProvider::FOS_USER_PWD_RESETTING_TEMPLATE, $templateProvider->getTemplateFor(AzineTemplateProvider::FOS_USER_PWD_RESETTING_TYPE));
		$this->assertEquals(AzineTemplateProvider::FOS_USER_REGISTRATION_TEMPLATE, $templateProvider->getTemplateFor(AzineTemplateProvider::FOS_USER_REGISTRATION_TYPE));
		$this->assertEquals(AzineTemplateProvider::BASE_TEMPLATE, $templateProvider->getTemplateFor("some undefined string"));

	}

	public function testIsFileAllowed(){
		$mocks = $this->getMockSetup();
		$templateProvider = new AzineTemplateProvider($mocks['router'], $mocks['translator'], $mocks['params']);

		$allowed1 = $mocks['params'][AzineEmailExtension::TEMPLATE_IMAGE_DIR]."/logo.png";
		$key = $templateProvider->isFileAllowed($allowed1);
		$this->assertTrue(is_string($key), "$allowed1 is not allowed, but it should!");


		$allowed2 = $mocks['params'][AzineEmailExtension::ALLOWED_IMAGES_FOLDERS][0]."/logo.png";
		$this->assertTrue(is_string($templateProvider->isFileAllowed($allowed2)), "$allowed2 is not allowed, but it should!");

		$notAllowed = __FILE__;
		$this->assertFalse(is_string($templateProvider->isFileAllowed($notAllowed)), "$notAllowed is allowed, but it should not!");


		$this->assertTrue(is_dir($templateProvider->getFolderFrom($key)));
		$this->assertFalse(is_dir($templateProvider->getFolderFrom("noKey")));

	}

	public function testMakeImagePathsWebRelative(){
		$mocks = $this->getMockSetup();
		$templateProvider = new AzineTemplateProvider($mocks['router'], $mocks['translator'], $mocks['params']);
		$locale = "en";

		$contentVars = array('testVar' => 'testValue');
		$contentVars[AzineTemplateProvider::CONTENT_ITEMS] = array(array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('otherTestVar' => 'otherTestValue')));
		$contentVars = $templateProvider->addTemplateVariablesFor(AzineTemplateProvider::BASE_TEMPLATE, $contentVars);
		$contentVars = $templateProvider->addTemplateSnippetsWithImagesFor(AzineTemplateProvider::BASE_TEMPLATE, $contentVars, $locale);

		$relativeVars = $templateProvider->makeImagePathsWebRelative($contentVars, $locale);
		$this->assertTrue(is_file(realpath($contentVars['logo_png'])));
		$this->assertNotEquals($relativeVars['logo_png'], $contentVars['logo_png']);

		$contentItemImage = $contentVars[AzineTemplateProvider::CONTENT_ITEMS][0][AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE]['logo_png'];
		$contentItemImage2 = $relativeVars[AzineTemplateProvider::CONTENT_ITEMS][0][AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE]['logo_png'];
		$this->assertTrue(is_file(realpath($contentItemImage)));
		$this->assertNotEquals($contentItemImage, $contentItemImage2);
	}

	public function testGetWebViewTokenId(){
		$mocks = $this->getMockSetup();
		$templateProvider = new AzineTemplateProvider($mocks['router'], $mocks['translator'], $mocks['params']);
		$this->assertEquals(AzineTemplateProvider::EMAIL_WEB_VIEW_TOKEN, $templateProvider->getWebViewTokenId());
	}

	public function testSaveWebViewFor() {
		$mocks = $this->getMockSetup();
		$templateProvider = new AzineTemplateProvider($mocks['router'], $mocks['translator'], $mocks['params']);

		$this->assertFalse($templateProvider->saveWebViewFor(AzineTemplateProvider::FOS_USER_PWD_RESETTING_TEMPLATE));
		$this->assertFalse($templateProvider->saveWebViewFor(AzineTemplateProvider::FOS_USER_REGISTRATION_TEMPLATE));
		$this->assertTrue($templateProvider->saveWebViewFor(AzineTemplateProvider::NEWSLETTER_TEMPLATE));
		$this->assertTrue($templateProvider->saveWebViewFor(AzineTemplateProvider::NOTIFICATIONS_TEMPLATE));
		$this->assertTrue($templateProvider->saveWebViewFor("some other string"));

	}

}
