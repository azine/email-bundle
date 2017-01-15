<?php
namespace Azine\EmailBundle\Tests\Controller;
use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Services\AzineEmailTwigExtension;
use Azine\EmailBundle\Tests\FindInFileUtil;
use Azine\EmailBundle\Services\AzineTemplateProvider;
use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Controller\AzineEmailTemplateController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
/**
 *
 * @author d.businger
 *
 */
class AzineEmailTemplateControllerTest extends WebTestCase
{
    /**
     * delete all files from spool-folder
     */
    protected function setUp()
    {
    }
    public function renderResponseCallback($template, $params)
    {
        if ($template == "AzineEmailBundle:Webview:index.html.twig") {
            return new Response("indexPage-html :".print_r($params, true));
        } elseif ($template == "AzineEmailBundle:Webview:mail.not.available.html.twig") {
            return new Response("mail.not.available.html.twig :".print_r($params, true));
        } elseif ($template == AzineTemplateProvider::NEWSLETTER_TEMPLATE.".html.twig") {
            return new Response("newsletter-html <a href='http://testurl.com/'>bla</a>&nbsp;<a href='http://testurl.com/with/?param=1'>with param</a>:".print_r($params, true));
        } elseif ($template == AzineTemplateProvider::NEWSLETTER_TEMPLATE.".txt.twig") {
            return new Response("newsletter-text bla\n\n some url with param http://testurl.com/with/?param=1 in plain-text:".print_r($params, true));
        } else if($template == "A")
        throw new \Exception("unexpected template $template");
    }
    public function testIndexAction()
    {
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->setMethods(array('get'))->getMock();
        $requestMock->expects($this->once())->method('get')->will($this->returnValue("a-custom@email.com"));
        $webViewServiceMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineWebViewService")->disableOriginalConstructor()->getMock();
        $webViewServiceMock->expects($this->once())->method("getTemplatesForWebPreView")->will($this->returnValue(array(
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
        $containerMock->expects($this->exactly(3))->method("get")->will($this->returnValueMap(array(
                array('request', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $requestMock),
                array('azine_email_web_view_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $webViewServiceMock),
                array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock)
        )));
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $controller->indexAction( $requestMock );
    }
    public function testWebPreViewAction()
    {
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->setMethods(array('getLocale'))->getMock();
        $requestMock->expects($this->exactly(3))->method("getLocale")->will($this->returnValue("en"));
        $requestMock->query = new ParameterBag();
        $webViewServiceMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineWebViewService")->disableOriginalConstructor()->getMock();
        $webViewServiceMock->expects($this->exactly(3))->method("getDummyVarsFor")->will($this->returnValue(array()));
        $twigMock = $this->getMockBuilder("Symfony\Bundle\TwigBundle\TwigEngine")->disableOriginalConstructor()->getMock();
        $twigMock->expects($this->exactly(3))->method("renderResponse")->will($this->returnCallback(array($this, 'renderResponseCallback')));
        $emailVars = array();
        $templateProviderMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();
        $templateProviderMock->expects($this->exactly(3))->method('addTemplateVariablesFor')->will($this->returnValue($emailVars));
        $templateProviderMock->expects($this->exactly(3))->method('makeImagePathsWebRelative')->will($this->returnValue($emailVars));
        $templateProviderMock->expects($this->exactly(3))->method('addTemplateSnippetsWithImagesFor')->will($this->returnValue($emailVars));
        $templateProviderMock->expects($this->exactly(3))->method('getCampaignParamsFor')->will($this->returnValue(array("utm_campaign" => "name", "utm_medium" => "medium")));
        $trackingCodeBuilderMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineEmailOpenTrackingCodeBuilder")->setConstructorArgs(array("http://www.google-analytics.com/?", array(
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME=> "utm_campaign",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM => "utm_term",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE => "utm_source",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM => "utm_medium",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT => "utm_content",
        )))->getMock();
        $trackingCodeBuilderMock->expects($this->exactly(3))->method('getTrackingImgCode')->will($this->returnValue("http://www.google-analytics.com/?"));
        $azineEmailTwigExtension = $this->getMockBuilder("Azine\EmailBundle\Services\AzineEmailTwigExtension")->disableOriginalConstructor()->getMock();
        $azineEmailTwigExtension->expects($this->exactly(3))->method("addCampaignParamsToAllUrls")->will($this->returnArgument(0));
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->exactly(24))->method("get")->will($this->returnValueMap(array(
                array('request', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $requestMock),
                array('azine_email_web_view_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $webViewServiceMock),
                array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock),
                array('azine_email_template_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $templateProviderMock),
                array('azine_email_email_open_tracking_code_builder', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $trackingCodeBuilderMock),
                array('azine.email.bundle.twig.filters', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $azineEmailTwigExtension),
        )));
        $containerMock->expects($this->exactly(3))->method("getParameter")->with("azine_email_no_reply")->will($this->returnValue(array('email' => "no-reply-email-mock@email.com", 'name' => 'no-reply-name')));
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $controller->webPreViewAction($requestMock, AzineTemplateProvider::NEWSLETTER_TEMPLATE);
        $controller->webPreViewAction($requestMock, AzineTemplateProvider::NEWSLETTER_TEMPLATE, "html");
        $response = $controller->webPreViewAction($requestMock, AzineTemplateProvider::NEWSLETTER_TEMPLATE, "txt");
        $this->assertEquals("text/plain", $response->headers->get("Content-Type"));
        $this->assertNotContains("<!doctype", $response->getContent());
    }
    public function testWebViewAction_User_access_allowed()
    {
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
        $securityTokenMock->expects($this->exactly(2))->method('getUser')->will($this->returnValue($userMock));
        $tokenStorageMock = $this->getMockBuilder("Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage")->disableOriginalConstructor()->getMock();
        $tokenStorageMock->expects($this->once())->method('getToken')->will($this->returnValue($securityTokenMock));
        $templateProviderMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();
        $templateProviderMock->expects($this->once())->method('getWebViewTokenId')->will($this->returnValue("tokenId"));
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->exactly(5))->method("get")->will($this->returnValueMap(array(
                array('azine_email_template_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $templateProviderMock),
                array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock),
                array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
                array('security.token_storage', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $tokenStorageMock),
        )));
        $containerMock->expects($this->once())->method("has")->with('security.token_storage')->will($this->returnValue(true));
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->getMock();
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $controller->webViewAction($requestMock, $token);
    }
    public function testWebViewAction_Anonymous_access_allowed()
    {
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
        $securityTokenMock->expects($this->never())->method('getUser');
        $tokenStorageMock = $this->getMockBuilder("Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage")->disableOriginalConstructor()->getMock();
        $tokenStorageMock->expects($this->never())->method('getToken');
        $templateProviderMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();
        $templateProviderMock->expects($this->once())->method('getWebViewTokenId')->will($this->returnValue("tokenId"));
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->exactly(4))->method("get")->will($this->returnValueMap(array(
                array('azine_email_template_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $templateProviderMock),
                array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock),
                array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
                array('security.token_storage', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $tokenStorageMock),
        )));
        $containerMock->expects($this->never())->method("has");
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->getMock();
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $controller->webViewAction($requestMock, $token);
    }
    /**
     * @expectedException Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testWebViewAction_User_access_denied()
    {
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
        $securityTokenMock->expects($this->exactly(2))->method('getUser')->will($this->returnValue($userMock));
        $tokenStorageMock = $this->getMockBuilder("Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage")->disableOriginalConstructor()->getMock();
        $tokenStorageMock->expects($this->once())->method('getToken')->will($this->returnValue($securityTokenMock));
        $translatorMock = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->setMethods(array('trans'))->getMock();
        $translatorMock->expects($this->once())->method("trans")->will($this->returnValue("translation"));
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->exactly(3))->method("get")->will($this->returnValueMap(array(
                array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
                array('security.token_storage', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $tokenStorageMock),
                array('translator', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $translatorMock),
        )));
        $containerMock->expects($this->once())->method("has")->with('security.token_storage')->will($this->returnValue(true));
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->getMock();
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $controller->webViewAction($requestMock, $token);
    }
    /**
     * @expectedException Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testWebViewAction_Anonymous_Access_denied()
    {
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
        $tokenStorageMock = $this->getMockBuilder("Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage")->disableOriginalConstructor()->getMock();
        $tokenStorageMock->expects($this->once())->method('getToken')->will($this->returnValue($securityTokenMock));
        $translatorMock = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->setMethods(array('trans'))->getMock();
        $translatorMock->expects($this->once())->method("trans")->will($this->returnValue("translation"));
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->exactly(3))->method("get")->will($this->returnValueMap(array(
                array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
                array('security.token_storage', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $tokenStorageMock),
                array('translator', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $translatorMock),
        )));
        $containerMock->expects($this->once())->method("has")->with('security.token_storage')->will($this->returnValue(true));
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->getMock();
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $controller->webViewAction($requestMock, $token);
    }
    public function testWebViewAction_Admin_with_CampaignParams()
    {
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
        $securityTokenMock->expects($this->exactly(2))->method('getUser')->will($this->returnValue($userMock));
        $tokenStorageMock = $this->getMockBuilder("Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage")->disableOriginalConstructor()->getMock();
        $tokenStorageMock->expects($this->once())->method('getToken')->will($this->returnValue($securityTokenMock));
        $translatorMock = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->getMock();
        $translatorMock->expects($this->any())->method('trans')->will($this->returnArgument(0));
        $templateProviderMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();
        $templateProviderMock->expects($this->once())->method('getWebViewTokenId')->will($this->returnValue("tokenId"));
        $templateProviderMock->expects($this->once())->method('getCampaignParamsFor')->will($this->returnValue(array("campaign" => "newsletter","keyword" => "2013-11-19")));
        $emailTwigExtension = new AzineEmailTwigExtension($templateProviderMock, $translatorMock, array('testurl.com'));
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->exactly(6))->method("get")->will($this->returnValueMap(array(
                array('azine_email_template_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $templateProviderMock),
                array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock),
                array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
                array('security.token_storage', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $tokenStorageMock),
                array('azine.email.bundle.twig.filters', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $emailTwigExtension),
        )));
        $containerMock->expects($this->once())->method("has")->with('security.token_storage')->will($this->returnValue(true));
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->getMock();
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $response = $controller->webViewAction($requestMock, $token);
        $this->assertContains("http://testurl.com/?campaign=newsletter&keyword=2013-11-19", $response->getContent());
        $this->assertContains('http://testurl.com/with/?param=1&campaign=newsletter&keyword=2013-11-19', $response->getContent());
    }
    public function testWebViewAction_MailNotFound()
    {
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
                array('templating', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $twigMock),
                array('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $doctrineManagerRegistryMock),
        )));
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->getMock();
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $controller->webViewAction($requestMock, $token);
    }
    public function testServeImageAction()
    {
        $folderKey = "asdfadfasfasfd";
        $filename = "testImage.png";
        $templateProviderMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();
        $templateProviderMock->expects($this->exactly(1))->method('getFolderFrom')->with($folderKey)->will($this->returnValue(__DIR__."/"));
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->exactly(1))->method("get")->will($this->returnValueMap(array(
                array('azine_email_template_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $templateProviderMock)
                    )));
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->getMock();
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $response = $controller->serveImageAction($requestMock, $folderKey, $filename);
        $this->assertEquals("image", $response->headers->get("Content-Type"));
        $this->assertEquals('inline; filename="'.$filename.'"', $response->headers->get('Content-Disposition'));
    }
    /**
     * @expectedException Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException
     */
    public function testServeImageAction_404()
    {
        $folderKey = "asdfadfasfasfd";
        $filename = "testImage.not.found.png";
        $templateProviderMock = $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();
        $templateProviderMock->expects($this->exactly(1))->method('getFolderFrom')->with($folderKey)->will($this->returnValue(false));
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->exactly(1))->method("get")->will($this->returnValueMap(array(
                array('azine_email_template_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $templateProviderMock)
        )));
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->getMock();
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $controller->serveImageAction($requestMock, $folderKey, $filename);
    }
    public function testSendTestEmailAction()
    {
        if (null !== static::$kernel) {
            static::$kernel->shutdown();
        }
        try {
            static::$kernel = static::createKernel(array());
        } catch (\RuntimeException $ex) {
            $this->markTestSkipped("There does not seem to be a full application available (e.g. running tests on travis.org). So this test is skipped.");
            return;
        }
        static::$kernel->boot();
        $container = static::$kernel->getContainer();
        $spoolDir = $container->getParameter('swiftmailer.spool.defaultMailer.file.path');
        // delete all spooled mails from other tests
        array_map('unlink', glob($spoolDir."/*.messag*"));
        array_map('unlink', glob($spoolDir."/.*.messag*"));
        $context = new RequestContext('/app.php');
        $context->setParameter('_locale', 'en');
        $router = $container->get('router');
        $router->setContext($context);
        $to = md5(time()."to").'@email.non-existent.to.mail.domain.com';
        $uri = $router->generate("azine_email_send_test_email", array('template' => AzineTemplateProvider::NEWSLETTER_TEMPLATE, 'email' => $to));
        $container->set('request', Request::create($uri, "GET"));
        // "login" a user
        $token = new UsernamePasswordToken("username", "password", "main");
        $recipientProvider = $container->get('azine_email_recipient_provider');
        $users = $recipientProvider->getNewsletterRecipientIDs();
        $token->setUser($recipientProvider->getRecipient($users[0]));
        $container->get('security.token_storage')->setToken($token);
        $container->get('request')->setSession(new Session(new MockFileSessionStorage()));
        // instantiate the controller and try to send the email
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($container);
        $response = $controller->sendTestEmailAction($container->get('request'), AzineTemplateProvider::NEWSLETTER_TEMPLATE, $to);
        $this->assertEquals(302, $response->getStatusCode(), "Status-Code 302 expected.");
        $uri = $router->generate("azine_email_template_index");
        $this->assertContains("Redirecting to $uri", $response->getContent(), "Redirect expected.");
        $findInFile = new FindInFileUtil();
        $findInFile->excludeMode = false;
        $findInFile->formats = array(".message");
        $this->assertEquals(1, sizeof($findInFile->find($spoolDir, "This is just the default content-block.")));
        $this->assertEquals(1, sizeof($findInFile->find($spoolDir, "Add some html content here")));
}
    public function testGetSpamIndexReportForSwiftMessage()
    {
        $swiftMessage = new \Swift_Message();
        $swiftMessage->setFrom("from@email.com");
        $swiftMessage->setTo("to@email.com");
        $swiftMessage->setSubject("a subject.");
        $swiftMessage->addPart("Hello dude,
================================================================================
Add some content here
This is just the default content-block.
Best regards,
the azine team
________________________________________________________________________________
azine ist ein Service von Azine IT Services AG
© 2013 by Azine IT Services AG
Füge \"no-reply@some.host.com\" zu deinem Adressbuch hinzu, um den Empfang von azine Mails sicherzustellen.
- Help / FAQs  :  https://some.host.com/app_dev.php/de/help
- AGB          :  https://some.host.com/app_dev.php/de/terms
- Über azine:  https://some.host.com/app_dev.php/de/about
- Kontakt      :  https://some.host.com/app_dev.php/de/contact
                ", 'text/plain');
        $swiftMessage->setBody("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\"><html xmlns=\"http://www.w3.org/1999/xhtml\"><head><title>azine – </title><meta name=\"description\" content=\"azine – \" /><meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" /></head><body style=\" color: #484B4C; margin:0; font: normal 12px/18px Arial, Helvetica, sans-serif; background-color: #fdfbfa;\"><table summary=\"header and logo\" width=\"640\" border=\"0\" align=\"center\" cellpadding=\"0\" cellspacing=\"0\" style=\"font: normal 12px/18px Arial, Helvetica, sans-serif;\"><tr><td>&nbsp;</td><td bgcolor=\"#f2f1f0\">&nbsp;</td><td>&nbsp;</td></tr><tr><td width=\"10\">&nbsp;</td><td width=\"620\" bgcolor=\"#f2f1f0\" style=\"padding: 0px 20px;\"><a href=\"http://azine\" target=\"_blank\" style=\"color: #9fb400; font-size: 55px; font-weight: bold; text-decoration: none;\"><img src=\"/app_dev.php/de/email/image/08f69bba117e6f02d40f07a5d84071e3/logo.png\"  height=\"35\" width=\"169\" alt=\"azine\" /></a>
                    &nbsp;<br /><span style='font-size: 16px; color:#484B4C; margin: 0px; padding: 0px;'>IT-Rekrutierung von morgen... weil du die beste Besetzung verdienst.</span></td><td width=\"10\">&nbsp;</td></tr></table><table summary='box with shadows' width='640' border='0' align='center' cellpadding='0' cellspacing='0'  style='font: normal 14px/18px Arial, Helvetica, sans-serif;'><tr><td colspan='3' width='640'><img width='640' height='10' src='/app_dev.php/de/email/image/08f69bba117e6f02d40f07a5d84071e3/topshadow.png' alt='' style='vertical-align: bottom;'/></td></tr><tr><td width='10' style='border-right: 1px solid #EEEEEE; background-image: url(\"/app_dev.php/de/email/image/08f69bba117e6f02d40f07a5d84071e3/left-shadow.png\");'>&nbsp;</td><td width=\"620\" bgcolor=\"white\"  style=\"padding:10px 20px 20px 20px; border-top: 1px solid #EEEEEE;\"><a name=\"top\" ></a><span style='color:#024d84; font:bold 16px Arial;'>Hallo dude,</span><p>
                        Add some content here
                    </p><p>
                        This is just the default content-block.
                    </p><p>
                        Freundliche Grüsse und bis bald,
                        <br/><span style=\"color:#024d84;\">dein azine Team</span></p></td><td width='10' style='border-left: 1px solid #EEEEEE; background-image: url(\"/app_dev.php/de/email/image/08f69bba117e6f02d40f07a5d84071e3/right-shadow.png\");'>&nbsp;</td></tr><tr><td width='10' style='border-right: 1px solid #EEEEEE; background-image: url(\"/app_dev.php/de/email/image/08f69bba117e6f02d40f07a5d84071e3/left-shadow.png\");'>&nbsp;</td><td width=\"620\" bgcolor=\"white\" style=\"text-align:center;\"><a href=\"http://azine\" target=\"_blank\" style=\"color: #9fb400; font-size: 32px; font-weight: bold; text-decoration: none; position:relative; top:1px;\"><img height=\"24\" width=\"116\" src=\"/app_dev.php/de/email/image/08f69bba117e6f02d40f07a5d84071e3/logo.png\" alt=\"azine\" /></a></td><td width='10' style='border-left: 1px solid #EEEEEE; background-image: url(\"/app_dev.php/de/email/image/08f69bba117e6f02d40f07a5d84071e3/right-shadow.png\");'>&nbsp;</td></tr><tr><td width='10' style='border-right: 1px solid #EEEEEE; background-image: url(\"/app_dev.php/de/email/image/08f69bba117e6f02d40f07a5d84071e3/left-shadow.png\");'>&nbsp;</td><td width=\"620\" align=\"center\" valign=\"top\" bgcolor=\"#434343\" style=\"font: normal 12px/18px Arial, Helvetica, sans-serif; padding:10px 30px 30px 30px; border-top:3px solid #b1c800; text-align:center;\" ><p style=\"color:white;\"><a href='https://some.host.com/app_dev.php/de/' style='text-decoration:none;'><span style='color: #9fb400; font-size:110%;'>azine</span></a> ist ein Service angeboten von Azine IT Services AG.
                    </p><p style=\"color:white;\">
                        Füge \"<a style=\"color:#FFFFFF;\" href=\"mailto:azine &lt;no-reply@some.host.com&gt;\"><span style=\"color:#FFFFFF;\">no-reply@some.host.com</span></a>\" zu deinem Adressbuch hinzu, um den Empfang von <a href=\"http://azine\" style=\"color:white; text-decoration:none;\">azine</a> Mails sicherzustellen.
                    </p><p style=\"color:#9fb400;\">
                        &copy; 2013 by Azine IT Services AG
                    </p><p style=\"color:#acacac;\"><a style=\"color:#acacac; text-decoration:none;\" href=\"https://some.host.com/app_dev.php/de/help\">Hilfe / FAQs</a> |
                    <a style=\"color:#acacac; text-decoration:none;\" href=\"https://some.host.com/app_dev.php/de/terms\">AGB</a> |
                    <a style=\"color:#acacac; text-decoration:none;\" href=\"https://some.host.com/app_dev.php/de/about\">Über azine</a> |
                    <a style=\"color:#acacac; text-decoration:none;\" href=\"https://some.host.com/app_dev.php/de/contact\">Kontakt</a></p></td><td width='10' style='border-left: 1px solid #EEEEEE; background-image: url(\"/app_dev.php/de/email/image/08f69bba117e6f02d40f07a5d84071e3/right-shadow.png\");'>&nbsp;</td></tr></table>
<div id=\"sfwdt016a7f\" class=\"sf-toolbar\" style=\"display: none\"></div><script>/*<![CDATA[*/    Sfjs = (function () {        \"use strict\";        var noop = function () {},            profilerStorageKey = 'sf2/profiler/',            request = function (url, onSuccess, onError, payload, options) {                var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');                options = options || {};                xhr.open(options.method || 'GET', url, true);                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');                xhr.onreadystatechange = function (state) {                    if (4 === xhr.readyState && 200 === xhr.status) {                        (onSuccess || noop)(xhr);                    } elseif (4 === xhr.readyState && xhr.status != 200) {                        (onError || noop)(xhr);                    }                };                xhr.send(payload || '');            },            hasClass = function (el, klass) {                return el.className.match(new RegExp('\\b' + klass + '\\b'));            },            removeClass = function (el, klass) {                el.className = el.className.replace(new RegExp('\\b' + klass + '\\b'), ' ');            },            addClass = function (el, klass) {                if (!hasClass(el, klass)) { el.className += \" \" + klass; }            },            getPreference = function (name) {                if (!window.localStorage) {                    return null;                }                return localStorage.getItem(profilerStorageKey + name);            },            setPreference = function (name, value) {                if (!window.localStorage) {                    return null;                }                localStorage.setItem(profilerStorageKey + name, value);            };        return {            hasClass: hasClass,            removeClass: removeClass,            addClass: addClass,            getPreference: getPreference,            setPreference: setPreference,            request: request,            load: function (selector, url, onSuccess, onError, options) {                var el = document.getElementById(selector);                if (el && el.getAttribute('data-sfurl') !== url) {                    request(                        url,                        function (xhr) {                            el.innerHTML = xhr.responseText;                            el.setAttribute('data-sfurl', url);                            removeClass(el, 'loading');                            (onSuccess || noop)(xhr, el);                        },                        function (xhr) { (onError || noop)(xhr, el); },                        options                    );                }                return this;            },            toggle: function (selector, elOn, elOff) {                var i,                    style,                    tmp = elOn.style.display,                    el = document.getElementById(selector);                elOn.style.display = elOff.style.display;                elOff.style.display = tmp;                if (el) {                    el.style.display = 'none' === tmp ? 'none' : 'block';                }                return this;            }        }    })();/*]]>*/</script><script>/*<![CDATA[*/    (function () {                Sfjs.load(            'sfwdt016a7f',            '/app_dev.php/_wdt/016a7f',            function (xhr, el) {                el.style.display = -1 !== xhr.responseText.indexOf('sf-toolbarreset') ? 'block' : 'none';                if (el.style.display == 'none') {                    return;                }                if (Sfjs.getPreference('toolbar/displayState') == 'none') {                    document.getElementById('sfToolbarMainContent-016a7f').style.display = 'none';                    document.getElementById('sfToolbarClearer-016a7f').style.display = 'none';                    document.getElementById('sfMiniToolbar-016a7f').style.display = 'block';                } else {                    document.getElementById('sfToolbarMainContent-016a7f').style.display = 'block';                    document.getElementById('sfToolbarClearer-016a7f').style.display = 'block';                    document.getElementById('sfMiniToolbar-016a7f').style.display = 'none';                }            },            function (xhr) {                if (xhr.status !== 0) {                    confirm('An error occurred while loading the web debug toolbar (' + xhr.status + ': ' + xhr.statusText + ').\n\nDo you want to open the profiler?') && (window.location = '/app_dev.php/_profiler/016a7f');                }            }        );    })();/*]]>*/</script>
</body></html>", 'text/html');
        $controller = new AzineEmailTemplateController();
        $report = $controller->getSpamIndexReportForSwiftMessage($swiftMessage);
        if (array_key_exists('curlError', $report)) {
            $this->markTestIncomplete("It seems postmarks spam-check-service is unresponsive.\n\n".print_r($report, true));
        }
        $this->assertArrayHasKey("success", $report, "success was expected in report.\n\n".print_r($report, true));
        $this->assertArrayNotHasKey("curlError", $report, "curlError was not expected in report.\n\n".print_r($report, true));
        $this->assertArrayHasKey("message", $report, "message was expected in report.\n\n".print_r($report, true));
    }
    public function testCheckSpamScoreOfSentEmailAction()
    {
        $requestMock = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")->disableOriginalConstructor()->setMethods(array('get'))->getMock();
        $containerMock = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->once())->method("get")->will($this->returnValueMap(array(
                array('request', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $requestMock))));
        $controller = new AzineEmailTemplateController();
        $controller->setContainer($containerMock);
        $jsonResponse = $controller->checkSpamScoreOfSentEmailAction( $requestMock );
        $json = $jsonResponse->getContent();
        if (strpos($json, "Getting the spam-info failed") !== false) {
            $this->markTestIncomplete("It seems postmarks spam-check-service is unresponsive.\n\n$json");
        }
        $this->assertNotContains("Getting the spam-info failed.", $jsonResponse->getContent(), "Spamcheck returned:\n".$jsonResponse->getContent());
        $this->assertContains("SpamScore", $jsonResponse->getContent());
    }
}
