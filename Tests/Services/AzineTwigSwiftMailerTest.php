<?php
namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\Services\AzineEmailTwigExtension;
use Azine\EmailBundle\Services\AzineTemplateProvider;
use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Services\AzineTwigSwiftMailer;

class AzineTwigSwiftMailerTest extends \PHPUnit_Framework_TestCase
{
    private function getMockSetup($sendCallback)
    {
        $mocks['mailer'] = $this->getMockBuilder("\Swift_Mailer")->disableOriginalConstructor()->getMock();
        $mocks['mailer']->expects($this->once())->method('send')->will($this->returnCallback($sendCallback));
        $mocks['router'] = $this->getMockBuilder("Symfony\Component\Routing\Generator\UrlGeneratorInterface")->disableOriginalConstructor()->getMock();
        $mocks['twig'] = $this->getMockBuilder("\Twig_Environment")->disableOriginalConstructor()->getMock();
        $mocks['baseTemplateMock'] = $this->getMockBuilder("\Twig_Template")->disableOriginalConstructor()->setMethods(array('renderBlock'))->getMockForAbstractClass();
        $mocks['twig']->expects($this->once())->method('loadTemplate')->will($this->returnValue($mocks['baseTemplateMock']));

        $mocks['translator'] = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->getMock();
        $mocks['translator']->expects($this->any())->method('trans')->will($this->returnValue("azine.translation.mock"));

        $imagesDir = realpath(__DIR__."/../../Resources/htmlTemplateImages/");
        $mocks['templateProvider'] = new AzineTemplateProvider($mocks['router'], $mocks['translator'], array(	AzineEmailExtension::ALLOWED_IMAGES_FOLDERS => array($imagesDir),
                                                                                                                AzineEmailExtension::TEMPLATE_IMAGE_DIR => $imagesDir,
                                                                                                                AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME=> "utm_campaign",
                                                                                                                AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM => "utm_term",
                                                                                                                AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE => "utm_source",
                                                                                                                AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM => "utm_medium",
                                                                                                                AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT => "utm_content",
                                                                                                                ));
        $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();


        $mocks['entityManager'] = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();
        $mocks['managerRegistry'] = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
        $mocks['managerRegistry']->expects($this->any())->method("getManager")->will($this->returnValue($mocks['entityManager']));

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
        $mocks['router']->expects($this->once())->method('getContext')->will($this->returnValue($requestContext));

        $mocks['trackingCodeImgBuilder'] = $this->getMockBuilder("Azine\EmailBundle\Services\AzineEmailOpenTrackingCodeBuilder")->setConstructorArgs(array("https://www.google-analytics.com/?tid=blabla", array(AzineEmailExtension::ALLOWED_IMAGES_FOLDERS => array($imagesDir),
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME=> "utm_campaign",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM => "utm_term",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE => "utm_source",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM => "utm_medium",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT => "utm_content",
            )))->getMock();
        $mocks['trackingCodeImgBuilder']->expects($this->any())->method('getTrackingImgCode')->will($this->returnValue("<img src='https://www.google-analytics.com/?tid=blabla&utm_medium=email' style='border:0' alt='' />"));

        $mocks['emailTwigExtension'] = new AzineEmailTwigExtension($mocks['templateProvider'], $mocks['translator'], array('testurl.com'));

        return $mocks;
    }

    public function returnOne(\Swift_Mime_Message $message, &$failedRecipients = null){
        return 1;
    }

    public function returnOneValidateCampaignUrls(\Swift_Mime_Message $message, &$failedRecipients = null){
        $body = $message->getBody();

        // has a email-tracking-image at the end
        $this->assertContains("<img src='https://www.google-analytics.com/?tid=blabla", $body, "Email open tracking image not found.");

        // links have tracking-parameters
        $this->assertContains("&utm_medium=email", $body, "Email links are expected to have tracking parameters attached.");
        return 1;
    }

    public function returnZeroWithFailedAddress(\Swift_Mime_Message $message, &$failedRecipients = null){
        $failedRecipients[] = $message->getTo();
        return 0;
    }

    /**
     * @return \FOS\UserBundle\Model\UserInterface
     */
    private function getUserMock()
    {
        $user = $this->getMockBuilder("FOS\UserBundle\Model\UserInterface")->disableOriginalConstructor()->getMock();
        $user->expects($this->once())->method('getEmail')->will($this->returnValue("user@email.com"));
        $user->expects($this->any())->method('getConfirmationToken')->will($this->returnValue("aptrqi3o4pte:::token:::zfpguhask5jx0a9xukp"));

        return $user;
    }

    public function renderBlockCallback($name, $context = array(), $blocks = array())
    {
        if ($name == 'subject') {
            return "a subject";
        } elseif ($name == 'body_html') {
            $generatedImage = "";
            if (array_key_exists("embededUsedGeneratedImage", $context)) {
                $generatedImage = "<img src='".$context['embededUsedGeneratedImage']."' alt='generatedImage'>";
            }

            return  "<html><body><h1>a html body</h1>$generatedImage<a href='http://some.url.com/' ><img src='".$context['logo_png']."' alt='logo'></a><p>with a paragraph and <a href='https://foo.bar.com/index.php?q=4'>links</a>.</p></body><html>";
        } elseif ($name == 'body_text') {
            return "a text body \n \n with new lines.";
        }
        throw new \Exception("un-known block : '$name'");
    }

    public function generateCallback($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        if ($name == 'fos_user_registration_confirm') {
            return "http://azine.bundle.com/confirmation/url/".$parameters['token'];

        } elseif ($name == 'fos_user_resetting_reset') {
            return "http://azine.bundle.com/resetting/url/".$parameters['token'];

        } elseif ($name == 'azine_email_serve_template_image') {
            return "http://azine.bundle.com/image/url/logo.png";
        }
        throw new \Exception("un-expected route for url-generation : '$name'");
    }

    public function testSendSingleEmail()
    {
        $mocks = $this->getMockSetup(array($this, 'returnOneValidateCampaignUrls'));
        $mocks['baseTemplateMock']->expects($this->exactly(2))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));
        $mocks['translator']->expects($this->once())->method('getLocale')->will($this->returnValue("en"));
        $mocks['router']->expects($this->exactly(12))->method('generate')->will($this->returnCallback(array($this, 'generateCallback')));

        $azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['translator'], $mocks['templateProvider'], $mocks['managerRegistry'], $mocks['trackingCodeImgBuilder'], $mocks['emailTwigExtension'], $mocks['parameters']);

        $to = "to@mail.com";
        $toName = "ToName";
        $params = array("aKey" => "aValue", 'contentItems' => array(array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('someOtherKey' => 'someOtherValue'))));
        $template = AzineTemplateProvider::NEWSLETTER_TEMPLATE.".txt.twig";
        $emailLocale = "en";
        $subject = "custom subject";
        $azineMailer->sendSingleEmail($to, $toName, $subject, $params, $template, $emailLocale);

    }

    public function testSendSingleEmailFails()
    {
        $mocks = array();
        $mocks['mailer'] = $this->getMockBuilder("\Swift_Mailer")->disableOriginalConstructor()->getMock();
        $mocks['mailer']->expects($this->once())->method('send')->will($this->returnCallback(array($this, 'returnZeroWithFailedAddress')));
        $mocks['router'] = $this->getMockBuilder("Symfony\Component\Routing\Generator\UrlGeneratorInterface")->disableOriginalConstructor()->getMock();
        $mocks['twig'] = $this->getMockBuilder("\Twig_Environment")->disableOriginalConstructor()->getMock();
        $mocks['baseTemplateMock'] = $this->getMockBuilder("\Twig_Template")->disableOriginalConstructor()->setMethods(array('renderBlock'))->getMockForAbstractClass();
        $mocks['twig']->expects($this->once())->method('loadTemplate')->will($this->returnValue($mocks['baseTemplateMock']));

        $mocks['translator'] = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->getMock();
        $mocks['translator']->expects($this->any())->method('trans')->will($this->returnValue("azine.translation.mock"));

        $imagesDir = realpath(__DIR__."/../../Resources/htmlTemplateImages/");
        $mocks['templateProvider'] = new AzineTemplateProvider($mocks['router'], $mocks['translator'], array(	AzineEmailExtension::ALLOWED_IMAGES_FOLDERS => array($imagesDir),
            AzineEmailExtension::TEMPLATE_IMAGE_DIR => $imagesDir,
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME=> "utm_campaign",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM => "utm_term",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE => "utm_source",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM => "utm_medium",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT => "utm_content",
        ));
        $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();

        $mocks['entityManager'] = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();
        $mocks['managerRegistry'] = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
        $mocks['managerRegistry']->expects($this->any())->method("getManager")->will($this->returnValue($mocks['entityManager']));

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
        $mocks['router']->expects($this->once())->method('getContext')->will($this->returnValue($requestContext));

        $mocks['trackingCodeImgBuilder'] = $this->getMockBuilder("Azine\EmailBundle\Services\AzineEmailOpenTrackingCodeBuilder")->setConstructorArgs(array("https://www.google-analytics.com/?tid=blabla", array(AzineEmailExtension::ALLOWED_IMAGES_FOLDERS => array($imagesDir),
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME=> "utm_campaign",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM => "utm_term",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE => "utm_source",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM => "utm_medium",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT => "utm_content",
        )))->getMock();

        $mocks['baseTemplateMock']->expects($this->exactly(2))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));
        $mocks['translator']->expects($this->once())->method('getLocale')->will($this->returnValue("en"));

        $mocks['emailTwigExtension'] = $this->getMockBuilder("Azine\EmailBundle\Services\AzineEmailTwigExtension")->disableOriginalConstructor()->getMock();
        $mocks['emailTwigExtension']->expects($this->exactly(1))->method("addCampaignParamsToAllUrls")->will($this->returnArgument(0));

        $azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['translator'], $mocks['templateProvider'], $mocks['managerRegistry'], $mocks['trackingCodeImgBuilder'], $mocks['emailTwigExtension'], $mocks['parameters']);

        $to = "to@mail.com";
        $toName = "ToName";
        $params = array("aKey" => "aValue", 'contentItems' => array(array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('someOtherKey' => 'someOtherValue'))));
        $template = AzineTemplateProvider::NEWSLETTER_TEMPLATE.".txt.twig";
        $emailLocale = "en";
        $subject = "custom subject";
        $result = $azineMailer->sendSingleEmail($to, $toName, $subject, $params, $template, $emailLocale);
        $this->assertFalse($result, "expected send to fail");
    }

    public function testSendEmailWithEmailLocaleAndAttachments()
    {
        $mocks = $this->getMockSetup(array($this, 'returnOne'));
        $mocks['baseTemplateMock']->expects($this->exactly(2))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));
        $mocks['translator']->expects($this->once())->method('getLocale')->will($this->returnValue("en"));
        $mocks['router']->expects($this->exactly(6))->method('generate')->will($this->returnCallback(array($this, 'generateCallback')));

        $azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['translator'], $mocks['templateProvider'], $mocks['managerRegistry'], $mocks['trackingCodeImgBuilder'], $mocks['emailTwigExtension'], $mocks['parameters']);

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
        $generatedImage = imagecreate(100, 100);
        $background_color = imagecolorallocate($generatedImage, 0, 0, 0);
        $text_color = imagecolorallocate($generatedImage, 233, 14, 91);
        imagestring($generatedImage, 1, 5, 5,  "A Simple Text String", $text_color);
        $template = AzineTemplateProvider::NEWSLETTER_TEMPLATE.".txt.twig";

        // embed a regular file, a generated file and an invalid file
        $params['embededUnusedFile'] = __FILE__;
        $params['embededUnusedGeneratedFile'] = $generatedImage;
        $params['embededUsedGeneratedImage'] = $generatedImage;
        $params['embededUnusedInexistentFile'] = __FILE__."not.existent.jpg";

        // attach a regular file and a generated file
        $attachments = array('regularFile' => __FILE__, 'generatedFile' => $generatedImage, "fileWithVeryShortName.replacement.txt" => __DIR__."/a.b");
        $emailLocale = "en";

        $azineMailer->sendEmail($failedRecipients, $subject, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, $params, $template, $attachments, $emailLocale);
    }

    /**
     * @expectedException  Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    public function testSendEmailWithEmailLocaleAndInexistentAttachment()
    {
        $mocks['mailer'] = $this->getMockBuilder("\Swift_Mailer")->disableOriginalConstructor()->getMock();
        $mocks['mailer']->expects($this->never())->method('send');

        $mocks['router'] = $this->getMockBuilder("Symfony\Component\Routing\Generator\UrlGeneratorInterface")->disableOriginalConstructor()->getMock();
        $mocks['twig'] = $this->getMockBuilder("\Twig_Environment")->disableOriginalConstructor()->getMock();
        $mocks['baseTemplateMock'] = $this->getMockBuilder("\Twig_Template")->disableOriginalConstructor()->setMethods(array('renderBlock'))->getMockForAbstractClass();
        $mocks['twig']->expects($this->once())->method('loadTemplate')->will($this->returnValue($mocks['baseTemplateMock']));

        $mocks['translator'] = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->getMock();
        $mocks['translator']->expects($this->any())->method('trans')->will($this->returnValue("azine.translation.mock"));

        $imagesDir = realpath(__DIR__."/../../Resources/htmlTemplateImages/");
        $mocks['templateProvider'] = new AzineTemplateProvider($mocks['router'], $mocks['translator'], array(	AzineEmailExtension::ALLOWED_IMAGES_FOLDERS => array($imagesDir),
                                                                                                                AzineEmailExtension::TEMPLATE_IMAGE_DIR => $imagesDir,
                                                                                                                AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME=> "utm_campaign",
                                                                                                                AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM => "utm_term",
                                                                                                                AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE => "utm_source",
                                                                                                                AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM => "utm_medium",
                                                                                                                AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT => "utm_content",
                                                                                                            ));
        $this->getMockBuilder("Azine\EmailBundle\Services\AzineTemplateProvider")->disableOriginalConstructor()->getMock();

        $mocks['entityManager'] = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();
        $mocks['managerRegistry'] = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
        $mocks['managerRegistry']->expects($this->any())->method("getManager")->will($this->returnValue($mocks['entityManager']));

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
        $mocks['router']->expects($this->once())->method('getContext')->will($this->returnValue($requestContext));
        $mocks['baseTemplateMock']->expects($this->exactly(2))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));
        $mocks['translator']->expects($this->once())->method('getLocale')->will($this->returnValue("en"));
        $mocks['router']->expects($this->never())->method('generate');

        $mocks['trackingCodeImgBuilder'] = $this->getMockBuilder("Azine\EmailBundle\Services\AzineEmailOpenTrackingCodeBuilder")->setConstructorArgs(array("http://www.google-analytics.com/?", array(AzineEmailExtension::ALLOWED_IMAGES_FOLDERS => array($imagesDir),
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_NAME=> "utm_campaign",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_TERM => "utm_term",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_SOURCE => "utm_source",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_MEDIUM => "utm_medium",
            AzineEmailExtension::TRACKING_PARAM_CAMPAIGN_CONTENT => "utm_content",
        )))->getMock();

        $mocks['emailTwigExtension'] = $this->getMockBuilder("Azine\EmailBundle\Services\AzineEmailTwigExtension")->disableOriginalConstructor()->getMock();
        $mocks['emailTwigExtension']->expects($this->exactly(1))->method("addCampaignParamsToAllUrls")->will($this->returnArgument(0));

        $azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['translator'], $mocks['templateProvider'], $mocks['managerRegistry'], $mocks['trackingCodeImgBuilder'], $mocks['emailTwigExtension'], $mocks['parameters']);

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

        // embed an inexistent file
        $params['embededUnusedInexistentFile'] = __FILE__."not.existent.jpg";

        // attach an inexistent file
        $attachments = array(__FILE__."not.existent.jpg");
        $emailLocale = "en";

        $azineMailer->sendEmail($failedRecipients, $subject, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, $params, $template, $attachments, $emailLocale);
    }

    public function testSendEmailWithOutEmailLocaleAndNoAttachment()
    {
        $mocks = $this->getMockSetup(array($this, 'returnOne'));
        $mocks['baseTemplateMock']->expects($this->exactly(2))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));
        $mocks['translator']->expects($this->once())->method('getLocale')->will($this->returnValue("en"));
        $mocks['router']->expects($this->exactly(0))->method('generate')->will($this->returnCallback(array($this, 'generateCallback')));

        $azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['translator'], $mocks['templateProvider'], $mocks['managerRegistry'], $mocks['trackingCodeImgBuilder'], $mocks['emailTwigExtension'], $mocks['parameters']);

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

        $sentCount = $azineMailer->sendEmail($failedRecipients, $subject, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, $params, $template, $attachments, $emailLocale);

        $this->assertEquals(1, $sentCount, "One email should have been sent.");
    }

    public function testSendConfirmationEmailMessage(){
        $azineMailer = $this->prepareForSendTest();
        $azineMailer->sendConfirmationEmailMessage($this->getUserMock());
    }

    public function testSendResettingEmailMessage(){
        $azineMailer = $this->prepareForSendTest();
        $azineMailer->sendResettingEmailMessage($this->getUserMock());
    }

    /**
     * @param $templateBaseId
     * @return AzineTwigSwiftMailer
     */
    private function prepareForSendTest(){
        $mocks = $this->getMockSetup(array($this, 'returnOne'));

        // as the subject from FOS-templates is embeded in the twig-template, the render-block is called 3 instead of only 2 times
        $mocks['baseTemplateMock']->expects($this->exactly(3))->method('renderBlock')->will($this->returnCallback(array($this, 'renderBlockCallback')));

        $mocks['parameters']['template'] = array();
        $mocks['parameters']['template']['confirmation'] = AzineTemplateProvider::FOS_USER_REGISTRATION_TEMPLATE.".txt.twig";
        $mocks['parameters']['template']['resetting'] = AzineTemplateProvider::FOS_USER_PWD_RESETTING_TEMPLATE.".txt.twig";
        $mocks['parameters']['from_email'] = array();
        $mocks['parameters']['from_email']['confirmation'] = 'from@email.com';
        $mocks['parameters']['from_email']['resetting'] = 'from@email.com';

        $mocks['router']->expects($this->once())->method('generate')->will($this->returnCallback(array($this, 'generateCallback')));

        $mocks['translator']->expects($this->exactly(2))->method('getLocale')->will($this->returnValue("en"));

        $azineMailer = new AzineTwigSwiftMailer($mocks['mailer'], $mocks['router'], $mocks['twig'], $mocks['translator'], $mocks['templateProvider'], $mocks['managerRegistry'], $mocks['trackingCodeImgBuilder'], $mocks['emailTwigExtension'], $mocks['parameters']);
        return $azineMailer;
    }
}
