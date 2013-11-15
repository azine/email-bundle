<?php
namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\Services\AzineTemplateProvider;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;

use Azine\EmailBundle\Services\AzineTwigSwiftMailer;

use Azine\EmailBundle\Services\AzineWebViewService;

class AzineTwigSwiftMailerTest extends \PHPUnit_Framework_TestCase {

	private function getMockSetup(){

		$mocks['mailer'] = $this->getMockBuilder("\Swift_Mailer")->disableOriginalConstructor()->getMock();
		$mocks['mailer']->expects($this->once())->method('send')->will($this->returnValue(1));
		$mocks['router'] = $this->getMockBuilder("Symfony\Component\Routing\Generator\UrlGeneratorInterface")->disableOriginalConstructor()->getMock();
		$mocks['twig'] = $this->getMockBuilder("\Twig_Environment")->disableOriginalConstructor()->getMock();
		$mocks['baseTemplateMock'] = $this->getMockBuilder("\Twig_Template")->disableOriginalConstructor()->setMethods(array('renderBlock'))->getMockForAbstractClass();
		$mocks['twig']->expects($this->once())->method('loadTemplate')->will($this->returnValue($mocks['baseTemplateMock']));

		$mocks['logger'] = $this->getMockBuilder("Monolog\Logger")->disableOriginalConstructor()->getMock();


		$mocks['translator'] = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->getMock();
		$mocks['translator']->expects($this->any())->method('trans')->will($this->returnValue("azine.translation.mock"));

		$imagesDir = realpath(__DIR__."/../../Resources/htmlTemplateImages/");
		$mocks['templateProvider'] = new AzineTemplateProvider($mocks['router'], $mocks['translator'], array(	AzineEmailExtension::ALLOWED_IMAGES_FOLDERS => array($imagesDir),
																												AzineEmailExtension::TEMPLATE_IMAGE_DIR => $imagesDir,
																												AzineEmailExtension::CAMPAIGN_PARAM_NAME => "pk_campaign",
																												AzineEmailExtension::CAMPAIGN_KEYWORD_PARAM_NAME => "pk_kwd",
																											));
		$this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();


		$mocks['entityManager'] = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();
		$mocks['parameters'] = array(	AzineEmailExtension::NO_REPLY => array(
																				AzineEmailExtension::NO_REPLY_EMAIL_ADDRESS => 'no-reply@address.com',
																				AzineEmailExtension::NO_REPLY_EMAIL_NAME => 'no-reply-name'),
										AzineTemplateProvider::CONTENT_ITEMS => array(
																						0 => array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('notification' => array('title' => 'some title', 'created' => new \DateTime('2 hours ago'), 'content' => "some content"))),
																						1 => array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('notification' => array('title' => 'some other title', 'created' => new \DateTime('1 hours ago'), 'content' => "some other content")))
																					),
										'logo_png' => $imagesDir."/logo.png",
										'noFile_png' => $imagesDir."/../../../unallowedFolder/logo.png",
										'not_allowed_png' => $imagesDir."/inexistentFile.png",
									);
		$requestContext = $this->getMockBuilder("Symfony\Component\Routing\RequestContext")->disableOriginalConstructor()->getMock();
		$requestContext->expects($this->once())->method("getHost")->will($this->returnValue("azine.test.host"));
		$mocks['router']->expects($this->once())->method('getContext')->will($this->returnValue($requestContext));

		return $mocks;
	}

	private function getUserMock(){
		$user = $this->getMockBuilder("FOS\UserBundle\Model\UserInterface")->disableOriginalConstructor()->getMock();
		$user->expects($this->once())->method('getEmail')->will($this->returnValue("user@email.com"));
		$user->expects($this->any())->method('getConfirmationToken')->will($this->returnValue("aptrqi3o4pte:::token:::zfpguhask5jx0a9xukp"));
		return $user;
	}

	public function renderBlockCallback($name, $context = array(), $blocks = array()){
		if($name == 'subject'){
			return "a subject";
		} else if ($name == 'body_html'){
			return  "<html><body><h1>a html body</h1><a href='http://some.url.com/' ><img src='".$context['logo_png']."'></a><p>with a paragraph and <a href='https://foo.bar.com/index.php?q=4'>links</a>.</p></body><html>";
		} else if ($name == 'body_text'){
			return "a text body \n \n with new lines.";
		}
		throw new \Exception("un-known block : '$name'");
	}

	public function generateCallback($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH){

		if($name == 'fos_user_registration_confirm'){
			return "http://azine.bundle.com/confirmation/url/".$parameters['token'];

		} else if ($name == 'fos_user_resetting_reset'){
			return "http://azine.bundle.com/resetting/url/".$parameters['token'];

		} else if ($name == 'azine_email_serve_template_image'){
			return "http://azine.bundle.com/image/url/logo.png";
		}
		throw new \Exception("un-expected route for url-generation : '$name'");
	}

 	public function testSendSingleEmail(){
 		$mocks = $this->getMockSetup();
  		$mocks['baseTemplateMock']->expects($this->exactly(2))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));
 		$mocks['translator']->expects($this->once())->method('getLocale')->will($this->returnValue("en"));
 		$mocks['router']->expects($this->exactly(6))->method('generate')->will($this->returnCallback(array($this, 'generateCallback')));

  		$azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['logger'], $mocks['translator'], $mocks['templateProvider'], $mocks['entityManager'], $mocks['parameters']);

  		$to = "to@mail.com";
  		$toName = "ToName";
  		$params = array("aKey" => "aValue");
  		$template = AzineTemplateProvider::NEWSLETTER_TEMPLATE.".txt.twig";
  		$emailLocale = "en";
  		$subject = "custom subject";
  		$azineMailer->sendSingleEmail($to, $toName, $subject, $params, $template, $emailLocale);

 	}

	public function testSendEmailWithEmailLocaleAndAttachment(){
		$mocks = $this->getMockSetup();
  		$mocks['baseTemplateMock']->expects($this->exactly(2))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));
		$mocks['translator']->expects($this->once())->method('getLocale')->will($this->returnValue("en"));
		$mocks['router']->expects($this->exactly(6))->method('generate')->will($this->returnCallback(array($this, 'generateCallback')));

		$azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['logger'], $mocks['translator'], $mocks['templateProvider'], $mocks['entityManager'], $mocks['parameters']);

		$failedRecipients = array();
		$from = "from@email.com";
		$fromName = "FromName";
		$to = "to@mail.com";
		$toName = "ToName";
		$cc = "cc@mail.com";
		$ccName = "CcName";
		$bcc = "bcc@email.com";
		$bccName = "BccName";
		$replyTo = "replyTo@email.com";
		$replyToName = "ReplyToName";
		$subject = "some dummy test subject";
		$params = array();
  		$template = AzineTemplateProvider::NEWSLETTER_TEMPLATE.".txt.twig";
		$attachments = array(__FILE__);
		$emailLocale = "en";

		$azineMailer->sendEmail($failedRecipients, $subject, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, $params, $template, $attachments, $emailLocale);
	}

	public function testSendEmailWithOutEmailLocaleAndNoAttachment(){
		$mocks = $this->getMockSetup();
  		$mocks['baseTemplateMock']->expects($this->exactly(2))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));
		$mocks['translator']->expects($this->once())->method('getLocale')->will($this->returnValue("en"));
		$mocks['router']->expects($this->exactly(6))->method('generate')->will($this->returnCallback(array($this, 'generateCallback')));

		$azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['logger'], $mocks['translator'], $mocks['templateProvider'], $mocks['entityManager'], $mocks['parameters']);

		$failedRecipients = array();
		$from = "from@email.com";
		$fromName = "FromName";
		$to = "to@mail.com";
		$toName = "ToName";
		$cc = "cc@mail.com";
		$ccName = "CcName";
		$bcc = "bcc@email.com";
		$bccName = "BccName";
		$replyTo = "replyTo@email.com";
		$replyToName = "ReplyToName";
		$subject = "some dummy test subject";
		$params = array();
		$template = AzineTemplateProvider::BASE_TEMPLATE.".txt.twig";
		$attachments = array();
		$emailLocale = null;

		$azineMailer->sendEmail($failedRecipients, $subject, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, $params, $template, $attachments, $emailLocale);
	}

	public function testSendConfirmationEmailMessage(){
 		$mocks = $this->getMockSetup();
  		$user = $this->getUserMock();

  		// as the subject from FOS-templates is embeded in the twig-template, the render-block is called 3 instead of only 2 times
  		$mocks['baseTemplateMock']->expects($this->exactly(3))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));

 		$mocks['parameters']['template'] = array();
 		$mocks['parameters']['template']['confirmation'] = AzineTemplateProvider::FOS_USER_REGISTRATION_TEMPLATE.".txt.twig";
 		$mocks['parameters']['from_email'] = array();
 		$mocks['parameters']['from_email']['confirmation'] = 'from@email.com';

 		$mocks['router']->expects($this->once())->method('generate')->will($this->returnCallback(array($this, 'generateCallback')));

 		$mocks['translator']->expects($this->exactly(2))->method('getLocale')->will($this->returnValue("en"));

  		$azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['logger'], $mocks['translator'], $mocks['templateProvider'], $mocks['entityManager'], $mocks['parameters']);

		$azineMailer->sendConfirmationEmailMessage($user);
	}

	public function testSendResettingEmailMessage(){
	 	$mocks = $this->getMockSetup();
	  	$user = $this->getUserMock();

	  	// as the subject from FOS-templates is embeded in the twig-template, the render-block is called 3 instead of only 2 times
  		$mocks['baseTemplateMock']->expects($this->exactly(3))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));

	 	$mocks['parameters']['template'] = array();
	 	$mocks['parameters']['template']['resetting'] = AzineTemplateProvider::FOS_USER_PWD_RESETTING_TEMPLATE.".txt.twig";
	 	$mocks['parameters']['from_email'] = array();
	 	$mocks['parameters']['from_email']['resetting'] = 'from@email.com';

	 	$mocks['translator']->expects($this->exactly(2))->method('getLocale')->will($this->returnValue("en"));

		$mocks['router']->expects($this->once())->method('generate')->will($this->returnCallback(array($this, 'generateCallback')));

		$azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['logger'], $mocks['translator'], $mocks['templateProvider'], $mocks['entityManager'], $mocks['parameters']);

		$azineMailer->sendResettingEmailMessage($user);

	}
}
