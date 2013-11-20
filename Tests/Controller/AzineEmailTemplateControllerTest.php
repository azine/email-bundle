<?php
namespace Azine\EmailBundle\Tests\Controller;


use Azine\EmailBundle\Services\AzineTemplateProvider;

use Azine\EmailBundle\Entity\SentEmail;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\HttpFoundation\Response;

use Azine\EmailBundle\Controller\AzineEmailTemplateController;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 *
 * @author d.businger
 *
 */
class AzineEmailTemplateControllerTest extends WebTestCase {

	public function renderResponseCallback($template, $params){
		if($template == "AzineEmailBundle:Webview:index.html.twig" ){
			return new Response("indexPage-html :".print_r($params, true));

		} else if($template == "AzineEmailBundle:Webview:mail.not.available.html.twig"){
			return new Response("mail.not.available.html.twig :".print_r($params, true));

		} else if ($template == AzineTemplateProvider::NEWSLETTER_TEMPLATE.".html.twig"){
			return new Response("newsletter-html <a href='http://testurl.com/'>bla</a>&nbsp;<a href='http://testurl.com/with/?param=1'>with param</a>:".print_r($params, true));

		} else if($template == "A")
		throw new \Exception("unexpected template $template");
	}

	public function testIndexAction() {
		$requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->setMethods(array('get'))->getMock();
		$requestMock->expects($this->once())->method('get')->will($this->returnValue("a-custom@email.com"));

		$webViewServiceMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineWebViewService")->disableOriginalConstructor()->getMock();
		$webViewServiceMock->expects($this->once())->method("getTemplatesForWebView")->will($this->returnValue(array(
				array(	'url' 			=> "azine_email_web_preview/newsletter",
						'description'	=> "Newsletter Template",
						'formats' 		=> array('html','txt'),
						'templateId'	=> AzineTemplateProvider::NEWSLETTER_TEMPLATE,
				),
				array(	'url' 			=> "azine_email_web_preview/notifications",
						'description'	=> "Notifications Template",
						'formats' 		=> array('html','txt'),
						'templateId'	=> AzineTemplateProvider::NOTIFICATIONS_TEMPLATE,
				),
		)));
		$webViewServiceMock->expects($this->once())->method("getTestMailAccounts")->will($this->returnValue(array(
				array('accountDescription' => "Gmail", 'accountEmail' => "some-account@gmail.com" ),
				array('accountDescription' => "GMX", 'accountEmail' => "some-account@gmx.com" ),
		)));

		$twigMock = $this->getMockBuilder("Symfony\Bundle\TwigBundle\TwigEngine")->disableOriginalConstructor()->getMock();
		$twigMock->expects($this->once())->method("renderResponse")->will($this->returnCallback(array($this, 'renderResponseCallback')));

		$containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
		$containerMock->expects($this->exactly(4))->method("get")->will($this->returnValueMap(array(
				array('request', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $requestMock),
				array('azine_email_web_view_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $webViewServiceMock),
				array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock)
		)));




		$controller = new AzineEmailTemplateController();
		$controller->setContainer($containerMock);
		$response = $controller->indexAction();
	}

// 	public function testWebPreViewAction(){
//      // not yet implemented
// 		$controller = new AzineEmailTemplateController();
// 		$controller->setContainer($this->getMockSetup());
// 		$response = $controller->webPreViewAction(AzineTemplateProvider::NEWSLETTER_TEMPLATE);

// 		$response = $controller->webPreViewAction(AzineTemplateProvider::NEWSLETTER_TEMPLATE, "txt");

// 	}

	public function testWebViewAction_User_access_allowed(){
		$token = "fdasdfasfafsadf";

		$twigMock = $this->getMockBuilder("Symfony\Bundle\TwigBundle\TwigEngine")->disableOriginalConstructor()->getMock();
		$twigMock->expects($this->once())->method("renderResponse")->will($this->returnCallback(array($this, 'renderResponseCallback')));

		$userMail = "a-user@email.com";
		$userMock = $this->getMockBuilder('FOS\UserBundle\Model\User')->getMock();
		$userMock->expects($this->once())->method("getEmail")->will($this->returnValue($userMail));

		$sentEmail = new SentEmail();
		$sentEmail->setRecipients(array($userMail));
		$sentEmail->setSent(new \DateTime("2 weeks ago"));
		$sentEmail->setTemplate(AzineTemplateProvider::NEWSLETTER_TEMPLATE);
		$sentEmail->setVariables(array());
		$sentEmail->setToken($token);

		$repositoryMock = $this->getMockBuilder("Azine\EmailBundle\Entity\Repositories\SentEmailRepository")->disableOriginalConstructor()->setMethods(array('findOneByToken'))->getMock();
		$repositoryMock->expects($this->once())->method("findOneByToken")->will($this->returnValue($sentEmail));

		$doctrineManagerMock = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();

		$doctrineManagerRegistryMock = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
		$doctrineManagerRegistryMock->expects($this->once())->method('getRepository')->with('AzineEmailBundle:SentEmail')->will($this->returnValue($repositoryMock));
		$doctrineManagerRegistryMock->expects($this->once())->method('getManager')->will($this->returnValue($this->returnValue($doctrineManagerMock)));

		$securityTokenMock = $this->getMockBuilder("Symfony\Component\Security\Core\Authentication\Token\TokenInterface")->disableOriginalConstructor()->getMock();
		$securityTokenMock->expects($this->once())->method('getUser')->will($this->returnValue($userMock));

		$securityContextMock = $this->getMockBuilder("Symfony\Component\Security\Core\SecurityContext")->disableOriginalConstructor()->getMock();
		$securityContextMock->expects($this->once())->method('getToken')->will($this->returnValue($securityTokenMock));

		$templateProviderMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();
		$templateProviderMock->expects($this->once())->method('getWebViewTokenId')->will($this->returnValue("tokenId"));

		$containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
		$containerMock->expects($this->exactly(5))->method("get")->will($this->returnValueMap(array(
				array('azine_email_template_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $templateProviderMock),
				array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock),
				array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
				array('security.context', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $securityContextMock),
		)));
		$containerMock->expects($this->once())->method("has")->with('security.context')->will($this->returnValue(true));



		$controller = new AzineEmailTemplateController();
		$controller->setContainer($containerMock);
		$response = $controller->webViewAction($token);
	}

	public function testWebViewAction_Anonymous_access_allowed(){
		$token = "fdasdfasfafsadf";

		$twigMock = $this->getMockBuilder("Symfony\Bundle\TwigBundle\TwigEngine")->disableOriginalConstructor()->getMock();
		$twigMock->expects($this->once())->method("renderResponse")->will($this->returnCallback(array($this, 'renderResponseCallback')));

		$sentEmail = new SentEmail();
		$sentEmail->setSent(new \DateTime("2 weeks ago"));
		$sentEmail->setTemplate(AzineTemplateProvider::NEWSLETTER_TEMPLATE);
		$sentEmail->setVariables(array());
		$sentEmail->setToken($token);

		$repositoryMock = $this->getMockBuilder("Azine\EmailBundle\Entity\Repositories\SentEmailRepository")->disableOriginalConstructor()->setMethods(array('findOneByToken'))->getMock();
		$repositoryMock->expects($this->once())->method("findOneByToken")->will($this->returnValue($sentEmail));

		$doctrineManagerMock = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();

		$doctrineManagerRegistryMock = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
		$doctrineManagerRegistryMock->expects($this->once())->method('getRepository')->with('AzineEmailBundle:SentEmail')->will($this->returnValue($repositoryMock));
		$doctrineManagerRegistryMock->expects($this->once())->method('getManager')->will($this->returnValue($this->returnValue($doctrineManagerMock)));

		$securityTokenMock = $this->getMockBuilder("Symfony\Component\Security\Core\Authentication\Token\TokenInterface")->disableOriginalConstructor()->getMock();
		$securityTokenMock->expects($this->once())->method('getUser')->will($this->returnValue(null));

		$securityContextMock = $this->getMockBuilder("Symfony\Component\Security\Core\SecurityContext")->disableOriginalConstructor()->getMock();
		$securityContextMock->expects($this->once())->method('getToken')->will($this->returnValue($securityTokenMock));

		$templateProviderMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();
		$templateProviderMock->expects($this->once())->method('getWebViewTokenId')->will($this->returnValue("tokenId"));

		$containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
		$containerMock->expects($this->exactly(5))->method("get")->will($this->returnValueMap(array(
				array('azine_email_template_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $templateProviderMock),
				array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock),
				array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
				array('security.context', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $securityContextMock),
		)));
		$containerMock->expects($this->once())->method("has")->with('security.context')->will($this->returnValue(true));



		$controller = new AzineEmailTemplateController();
		$controller->setContainer($containerMock);
		$response = $controller->webViewAction($token);
	}

	/**
 	 * @expectedException Symfony\Component\Security\Core\Exception\AccessDeniedException
	 */
	public function testWebViewAction_User_access_denied(){
		$token = "fdasdfasfafsadf";

		$userMail = "an-other-user@email.com";
		$userMock = $this->getMockBuilder('FOS\UserBundle\Model\User')->getMock();
		$userMock->expects($this->once())->method("getEmail")->will($this->returnValue($userMail));

		$sentEmail = new SentEmail();
		$sentEmail->setRecipients(array("someuser@email.com"));
		$sentEmail->setSent(new \DateTime("2 weeks ago"));
		$sentEmail->setTemplate(AzineTemplateProvider::NEWSLETTER_TEMPLATE);
		$sentEmail->setVariables(array());
		$sentEmail->setToken($token);

		$repositoryMock = $this->getMockBuilder("Azine\EmailBundle\Entity\Repositories\SentEmailRepository")->disableOriginalConstructor()->setMethods(array('findOneByToken'))->getMock();
		$repositoryMock->expects($this->once())->method("findOneByToken")->will($this->returnValue($sentEmail));

		$doctrineManagerMock = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();

		$doctrineManagerRegistryMock = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
		$doctrineManagerRegistryMock->expects($this->once())->method('getRepository')->with('AzineEmailBundle:SentEmail')->will($this->returnValue($repositoryMock));

		$securityTokenMock = $this->getMockBuilder("Symfony\Component\Security\Core\Authentication\Token\TokenInterface")->disableOriginalConstructor()->getMock();
		$securityTokenMock->expects($this->once())->method('getUser')->will($this->returnValue($userMock));

		$securityContextMock = $this->getMockBuilder("Symfony\Component\Security\Core\SecurityContext")->disableOriginalConstructor()->getMock();
		$securityContextMock->expects($this->once())->method('getToken')->will($this->returnValue($securityTokenMock));

		$translatorMock = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->setMethods(array('trans'))->getMock();
		$translatorMock->expects($this->once())->method("trans")->will($this->returnValue("translation"));


		$containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
		$containerMock->expects($this->exactly(3))->method("get")->will($this->returnValueMap(array(
				array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
				array('security.context', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $securityContextMock),
				array('translator', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $translatorMock),
		)));
		$containerMock->expects($this->once())->method("has")->with('security.context')->will($this->returnValue(true));

		$controller = new AzineEmailTemplateController();
		$controller->setContainer($containerMock);
		$response = $controller->webViewAction($token);
	}

	/**
 	 * @expectedException Symfony\Component\Security\Core\Exception\AccessDeniedException
	 */
	public function testWebViewAction_Anonymous_Access_denied(){
		$token = "fdasdfasfafsadf";

		$sentEmail = new SentEmail();
		$sentEmail->setRecipients(array("someuser@email.com"));
		$sentEmail->setSent(new \DateTime("2 weeks ago"));
		$sentEmail->setTemplate(AzineTemplateProvider::NEWSLETTER_TEMPLATE);
		$sentEmail->setVariables(array());
		$sentEmail->setToken($token);

		$repositoryMock = $this->getMockBuilder("Azine\EmailBundle\Entity\Repositories\SentEmailRepository")->disableOriginalConstructor()->setMethods(array('findOneByToken'))->getMock();
		$repositoryMock->expects($this->once())->method("findOneByToken")->will($this->returnValue($sentEmail));

		$doctrineManagerMock = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();

		$doctrineManagerRegistryMock = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
		$doctrineManagerRegistryMock->expects($this->once())->method('getRepository')->with('AzineEmailBundle:SentEmail')->will($this->returnValue($repositoryMock));

		$securityTokenMock = $this->getMockBuilder("Symfony\Component\Security\Core\Authentication\Token\TokenInterface")->disableOriginalConstructor()->getMock();
		$securityTokenMock->expects($this->once())->method('getUser')->will($this->returnValue(null));

		$securityContextMock = $this->getMockBuilder("Symfony\Component\Security\Core\SecurityContext")->disableOriginalConstructor()->getMock();
		$securityContextMock->expects($this->once())->method('getToken')->will($this->returnValue($securityTokenMock));

		$translatorMock = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->setMethods(array('trans'))->getMock();
		$translatorMock->expects($this->once())->method("trans")->will($this->returnValue("translation"));


		$containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
		$containerMock->expects($this->exactly(3))->method("get")->will($this->returnValueMap(array(
				array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
				array('security.context', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $securityContextMock),
				array('translator', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $translatorMock),
		)));
		$containerMock->expects($this->once())->method("has")->with('security.context')->will($this->returnValue(true));

		$controller = new AzineEmailTemplateController();
		$controller->setContainer($containerMock);
		$response = $controller->webViewAction($token);
	}

	public function testWebViewAction_Admin_with_CampaignParams(){
		$token = "fdasdfasfafsadf";

		$twigMock = $this->getMockBuilder("Symfony\Bundle\TwigBundle\TwigEngine")->disableOriginalConstructor()->getMock();
		$twigMock->expects($this->once())->method("renderResponse")->will($this->returnCallback(array($this, 'renderResponseCallback')));

		$userMock = $this->getMockBuilder('FOS\UserBundle\Model\User')->getMock();
		$userMock->expects($this->once())->method("getEmail")->will($this->returnValue("admin@email.com"));
		$userMock->expects($this->once())->method("hasRole")->with("ROLE_ADMIN")->will($this->returnValue(true));

		$sentEmail = new SentEmail();
		$sentEmail->setRecipients(array("a-user@email.com"));
		$sentEmail->setSent(new \DateTime("2 weeks ago"));
		$sentEmail->setTemplate(AzineTemplateProvider::NEWSLETTER_TEMPLATE);
		$sentEmail->setVariables(array());
		$sentEmail->setToken($token);

		$repositoryMock = $this->getMockBuilder("Azine\EmailBundle\Entity\Repositories\SentEmailRepository")->disableOriginalConstructor()->setMethods(array('findOneByToken'))->getMock();
		$repositoryMock->expects($this->once())->method("findOneByToken")->will($this->returnValue($sentEmail));

		$doctrineManagerMock = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();

		$doctrineManagerRegistryMock = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
		$doctrineManagerRegistryMock->expects($this->once())->method('getRepository')->with('AzineEmailBundle:SentEmail')->will($this->returnValue($repositoryMock));
		$doctrineManagerRegistryMock->expects($this->once())->method('getManager')->will($this->returnValue($this->returnValue($doctrineManagerMock)));

		$securityTokenMock = $this->getMockBuilder("Symfony\Component\Security\Core\Authentication\Token\TokenInterface")->disableOriginalConstructor()->getMock();
		$securityTokenMock->expects($this->once())->method('getUser')->will($this->returnValue($userMock));

		$securityContextMock = $this->getMockBuilder("Symfony\Component\Security\Core\SecurityContext")->disableOriginalConstructor()->getMock();
		$securityContextMock->expects($this->once())->method('getToken')->will($this->returnValue($securityTokenMock));

		$templateProviderMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();
		$templateProviderMock->expects($this->once())->method('getWebViewTokenId')->will($this->returnValue("tokenId"));
		$templateProviderMock->expects($this->once())->method('getCampaignParamsFor')->will($this->returnValue(array("campaign" => "newsletter","keyword" => "2013-11-19")));

		$containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
		$containerMock->expects($this->exactly(5))->method("get")->will($this->returnValueMap(array(
				array('azine_email_template_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $templateProviderMock),
				array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock),
				array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
				array('security.context', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $securityContextMock),
		)));
		$containerMock->expects($this->once())->method("has")->with('security.context')->will($this->returnValue(true));



		$controller = new AzineEmailTemplateController();
		$controller->setContainer($containerMock);
		$response = $controller->webViewAction($token);

		$this->assertContains("http://testurl.com/?campaign=newsletter&keyword=2013-11-19", $response->getContent());
		$this->assertContains('http://testurl.com/with/?param=1&campaign=newsletter&keyword=2013-11-19', $response->getContent());
	}

	public function testWebViewAction_MailNotFound(){
		$token = "fdasdfasfafsadf-not-found";

		$twigMock = $this->getMockBuilder("Symfony\Bundle\TwigBundle\TwigEngine")->disableOriginalConstructor()->getMock();
		$twigMock->expects($this->once())->method("renderResponse")->will($this->returnCallback(array($this, 'renderResponseCallback')));

		$repositoryMock = $this->getMockBuilder("Azine\EmailBundle\Entity\Repositories\SentEmailRepository")->disableOriginalConstructor()->setMethods(array('findOneByToken'))->getMock();
		$repositoryMock->expects($this->once())->method("findOneByToken")->will($this->returnValue(null));

		$doctrineManagerRegistryMock = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
		$doctrineManagerRegistryMock->expects($this->once())->method('getRepository')->with('AzineEmailBundle:SentEmail')->will($this->returnValue($repositoryMock));

		$containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
		$containerMock->expects($this->once())->method("getParameter")->with("azine_email_web_view_retention")->will($this->returnValue(123));
		$containerMock->expects($this->exactly(2))->method("get")->will($this->returnValueMap(array(
				//array('azine_email_template_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $templateProviderMock),
				array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock),
				array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
				//array('security.context', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $securityContextMock),
		)));

		$controller = new AzineEmailTemplateController();
		$controller->setContainer($containerMock);
		$response = $controller->webViewAction($token);
	}



// 	public function testServeImageAction(){
//      // not yet implemented
// 		$folderKey = "fdasdfasfafsadf";
// 		$fileName = "logo.png";
// 		$container = $this->getMockSetup();
// 		$controller = new AzineEmailTemplateController();
// 		$controller->setContainer($container);
// 		$response = $controller->serveImageAction($folderKey, $filename);

// 	}

// 	public function testSendTestEmailAction(){
//      // not yet implemented
// 		$template = AzineTemplateProvider::NEWSLETTER_TEMPLATE;
// 		$email = "some-adr@email.com";
// 		$container = $this->getMockSetup();
// 		$controller = new AzineEmailTemplateController();
// 		$controller->setContainer($container);
// 		//$response = $controller->sendTestEmailAction($template, $email);
//	}

// 	public function testCheckSpamScoreOfSentEmailAction(){
//     // not yet implemented
// 	}
}

