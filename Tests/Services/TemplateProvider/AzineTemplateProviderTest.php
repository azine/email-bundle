<?php
namespace Azine\EmailBundle\Tests\Services\TemplateProvider;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;



use Azine\EmailBundle\Services\AzineTemplateProvider;

use FOS\UserBundle\Security\EmailUserProvider;

class AzineTemplateProviderTest extends WebTestCase{

	private $templateProvider;


	protected function setUp(){
        static::$kernel = static::createKernel();
        static::$kernel->boot();
        $translator = static::$kernel->getContainer()->get('translator');
        $router = static::$kernel->getContainer()->get('router');
        $this->templateProvider = new AzineTemplateProvider($router, $translator, array(AzineEmailExtension::TEMPLATE_IMAGE_DIR => __DIR__));
    }



	public function testAddVariables(){
		$vars = array(	'logo_png' =>	'new_logo_value.png',
						'new_value' =>	'some new value',
					);
		$copy = $vars;

		$vars = $this->templateProvider->addTemplateVariablesFor("test", $vars);
		$sizeWithVars = sizeof($vars);
		$this->assertGreaterThan(sizeof($copy), $sizeWithVars, "There should be more items in the array after adding the variables.");

		foreach ($copy as $key => $value){
			$this->assertEquals($copy[$key], $vars[$key], "A customized value has been overridden with template-defaults. This should not happen.");
		}

	}

	public function testAddSnippets(){
		$vars = array(	'logo_png' =>	'new_logo_value.png',
						'new_value' =>	'some new value',
					);
		$copy = $vars;

		$vars = $this->templateProvider->addTemplateVariablesFor("test", $vars);
		$sizeWithVars = sizeof($vars);
		$this->assertGreaterThan(sizeof($copy), $sizeWithVars, "There should be more items in the array after adding the variables.");

		$vars = $this->templateProvider->addTemplateSnippetsWithImagesFor("test", $vars, "de");
		$sizeWithVars2 = sizeof($vars);
		$this->assertGreaterThan($sizeWithVars, $sizeWithVars2, "There should be more items in the array after adding the snippets.");

		foreach ($copy as $key => $value){
			$this->assertEquals($copy[$key], $vars[$key], "A customized value has been overridden with template-defaults. This should not happen.");
		}

	}

	/**
     * @expectedException \Exception
	 */
	public function testAddSnippetsWithoutVariables(){
		$this->templateProvider->addTemplateSnippetsWithImagesFor("test", array(), "de");
	}

	public function testGetImageDir(){
		$this->assertEquals(__DIR__, $this->templateProvider->getTemplateImageDir(), "Image-directory doesn't match.");
	}

	public function testGetTemplateFor(){
		$this->assertEquals(AzineTemplateProvider::BASE_TEMPLATE, $this->templateProvider->getTemplateFor("bla"), "Base-Template expected.");
	}
}
