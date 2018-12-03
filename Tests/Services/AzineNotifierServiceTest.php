<?php

namespace Azine\EmailBundle\Tests\Services;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Entity\Notification;
use Azine\EmailBundle\Entity\RecipientInterface;
use Azine\EmailBundle\Services\AzineNotifierService;
use Azine\EmailBundle\Services\AzineTemplateProvider;
use Azine\EmailBundle\Services\ExampleNotifierService;

class AzineNotifierServiceTest extends \PHPUnit\Framework\TestCase
{
    private function getMockSetup()
    {
        $mocks = array();
        $mocks['mailer'] = $this->getMockBuilder("Azine\EmailBundle\Services\TemplateTwigSwiftMailerInterface")->disableOriginalConstructor()->getMock();
        $mocks['twig'] = $this->getMockBuilder("\Twig_Environment")->disableOriginalConstructor()->getMock();
        $mocks['router'] = $this->getMockBuilder("Symfony\Component\Routing\Generator\UrlGeneratorInterface")->disableOriginalConstructor()->getMock();
        $mocks['entityManager'] = $this->getMockBuilder("Doctrine\ORM\EntityManager")->disableOriginalConstructor()->getMock();
        $mocks['notificationRepository'] = $this->getMockBuilder("Azine\EmailBundle\Entity\Repositories\NotificationRepository")->disableOriginalConstructor()->getMock();
        $mocks['managerRegistry'] = $this->getMockBuilder("Doctrine\Common\Persistence\ManagerRegistry")->disableOriginalConstructor()->getMock();
        $mocks['managerRegistry']->expects($this->any())->method('getManager')->will($this->returnValue($mocks['entityManager']));
        $mocks['managerRegistry']->expects($this->any())->method('getRepository')->will($this->returnValue($mocks['notificationRepository']));
        $mocks['templateProvider'] = $this->getMockBuilder("Azine\EmailBundle\Services\TemplateProviderInterface")->disableOriginalConstructor()->getMock();
        $mocks['recipientProvider'] = $this->getMockBuilder("Azine\EmailBundle\Services\RecipientProviderInterface")->disableOriginalConstructor()->getMock();
        $mocks['translator'] = $this->getMockBuilder("Symfony\Bundle\FrameworkBundle\Translation\Translator")->disableOriginalConstructor()->getMock();
        $mocks['parameters'] = array(
                                AzineEmailExtension::NEWSLETTER.'_'.AzineEmailExtension::NEWSLETTER_INTERVAL => '7',
                                AzineEmailExtension::NEWSLETTER.'_'.AzineEmailExtension::NEWSLETTER_SEND_TIME => '09:00',
                                AzineEmailExtension::TEMPLATES.'_'.AzineEmailExtension::NEWSLETTER_TEMPLATE => AzineTemplateProvider::NEWSLETTER_TEMPLATE,
                                AzineEmailExtension::TEMPLATES.'_'.AzineEmailExtension::NOTIFICATIONS_TEMPLATE => AzineTemplateProvider::NOTIFICATIONS_TEMPLATE,
                                AzineEmailExtension::TEMPLATES.'_'.AzineEmailExtension::CONTENT_ITEM_TEMPLATE => AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE,
                                    );

        return $mocks;
    }

    public function testAddNotification()
    {
        $mocks = $this->getMockSetup();
        $mocks['entityManager']->expects($this->once())->method('persist');
        $notifier = new AzineNotifierService($mocks['mailer'], $mocks['twig'], $mocks['router'], $mocks['managerRegistry'], $mocks['templateProvider'], $mocks['recipientProvider'], $mocks['translator'], $mocks['parameters']);

        $n = $notifier->addNotification('12', 'title', 'content', 'template', array('templateVars'), 1, false);

        $this->assertSame('12', $n->getRecipientId());
        $this->assertSame('title', $n->getTitle());
        $this->assertSame('template', $n->getTemplate());
        $this->assertSame(array('templateVars'), $n->getVariables());
    }

    public function testAddNotificationMessage()
    {
        $goToUrl = 'http://azine.email/this/is/a/url';
        $templateVars = array('logo_png' => '/some/directory/logo.png', 'mainColor' => 'green');
        $mocks = $this->getMockSetup();
        $mocks['entityManager']->expects($this->once())->method('persist');
        $mocks['templateProvider']->expects($this->once())->method('addTemplateVariablesFor')->with(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE, array('goToUrl' => $goToUrl))->will($this->returnValue(array_merge(array('goToUrl' => $goToUrl), $templateVars)));
        $notifier = new AzineNotifierService($mocks['mailer'], $mocks['twig'], $mocks['router'], $mocks['managerRegistry'], $mocks['templateProvider'], $mocks['recipientProvider'], $mocks['translator'], $mocks['parameters']);

        $notifier->addNotificationMessage('12', 'some title', "some content with \nline breaks.", $goToUrl);

        $mocks = $this->getMockSetup();
        $mocks['entityManager']->expects($this->once())->method('persist');
        $mocks['templateProvider']->expects($this->once())->method('addTemplateVariablesFor')->with(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE, array())->will($this->returnValue($templateVars));
        $notifier = new AzineNotifierService($mocks['mailer'], $mocks['twig'], $mocks['router'], $mocks['managerRegistry'], $mocks['templateProvider'], $mocks['recipientProvider'], $mocks['translator'], $mocks['parameters']);

        $notifier->addNotificationMessage('12', 'some title', "some content with \nline breaks.");
    }

    public function testSendNewsletter()
    {
        $failedAddresses = array();
        $recipientIds = array(11, 12, 13, 14);
        $mocks = $this->getMockSetup();
        $this->mockRecipients($mocks['recipientProvider'], $recipientIds);
        $mocks['recipientProvider']->expects($this->once())->method('getNewsletterRecipientIDs')->will($this->returnValue($recipientIds));
        $mocks['mailer']->expects($this->exactly(sizeof($recipientIds)))->method('sendSingleEmail')->will($this->returnCallback(array($this, 'sendSingleEmailCallBack')));

        $mocks['mailer']->expects($this->exactly(sizeof($recipientIds)))->method('sendSingleEmail');

        $notifier = new ExampleNotifierService($mocks['mailer'], $mocks['twig'], $mocks['router'], $mocks['managerRegistry'], $mocks['templateProvider'], $mocks['recipientProvider'], $mocks['translator'], $mocks['parameters']);
        $sentMails = $notifier->sendNewsletter($failedAddresses);
        $this->assertSame(1, sizeof($failedAddresses));
        $this->assertSame(sizeof($recipientIds) - 1, $sentMails);
    }

    public function sendSingleEmailCallBack($email, $displayName, $params, $wrapperTemplate, $locale)
    {
        if ('11mail@email.com' == $email) {
            return false;
        }

        return true;
    }

    public function testSendNewsletter_NoContent()
    {
        $failedAddresses = array();
        $recipientIds = array(11, 12, 13, 14);
        $mocks = $this->getMockSetup();
        $this->mockRecipients($mocks['recipientProvider'], $recipientIds);
        $mocks['recipientProvider']->expects($this->once())->method('getNewsletterRecipientIDs')->will($this->returnValue($recipientIds));

        $mocks['mailer']->expects($this->never())->method('sendSingleEmail');

        $notifier = new AzineNotifierService($mocks['mailer'], $mocks['twig'], $mocks['router'], $mocks['managerRegistry'], $mocks['templateProvider'], $mocks['recipientProvider'], $mocks['translator'], $mocks['parameters']);
        $sentMails = $notifier->sendNewsletter($failedAddresses);
        $this->assertSame(4, sizeof($failedAddresses), 'Email-addresses failed unexpectedly:'.print_r($failedAddresses, true));
        $this->assertSame(0, $sentMails, 'Not the expected number of sent emails.');
    }

    public function testSendNotificationsAzineNotifierService()
    {
        $failedAddresses = array();
        $recipientIds = array(11, 12, 13, 14);
        $mocks = $this->getMockSetup();
        $this->mockRecipients($mocks['recipientProvider'], $recipientIds);
        $mocks['mailer']->expects($this->exactly(sizeof($recipientIds)))->method('sendSingleEmail')->will($this->returnCallback(array($this, 'sendSingleEmailCallBack')));

        $notification = new Notification();
        $notification->setContent('bla bla');
        $notification->setCreated(new \DateTime());
        $notification->setImportance(0);
        $notification->setTemplate(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE);
        $notification->setVariables(array('blabla' => 'blablaValue'));
        $notification->setTitle('a title');

        $mocks['notificationRepository']->expects($this->once())->method('getNotificationRecipientIds')->will($this->returnValue($recipientIds));
        $mocks['notificationRepository']->expects($this->exactly(4))->method('getNotificationsToSend')->will($this->returnValue(array($notification)));
        $mocks['notificationRepository']->expects($this->never())->method('getNotificationsToSendImmediately');
        $mocks['notificationRepository']->expects($this->never())->method('markAllNotificationsAsSentFarInThePast');
        $mocks['notificationRepository']->expects($this->exactly(4))->method('getLastNotificationDate')->will($this->returnValue(new \DateTime('@0')));

        $notifier = new AzineNotifierService($mocks['mailer'], $mocks['twig'], $mocks['router'], $mocks['managerRegistry'], $mocks['templateProvider'], $mocks['recipientProvider'], $mocks['translator'], $mocks['parameters']);
        $sentMails = $notifier->sendNotifications($failedAddresses);
        $this->assertSame(1, sizeof($failedAddresses), 'One failed address was expected.');
        $sentMailCount = count($recipientIds) - count($failedAddresses);
        $this->assertSame($sentMailCount, $sentMails, "Not the right number of emails has been sent successfully. Expected $sentMailCount");
    }

    public function testSendNotificationsAzineNotifierService_NoNotifications()
    {
        $failedAddresses = array();
        $recipientIds = array(11, 12, 13, 14);
        $mocks = $this->getMockSetup();
        $this->mockRecipients($mocks['recipientProvider'], $recipientIds);
        $mocks['mailer']->expects($this->never())->method('sendSingleEmail')->will($this->returnCallback(array($this, 'sendSingleEmailCallBack')));

        $mocks['notificationRepository']->expects($this->once())->method('getNotificationRecipientIds')->will($this->returnValue($recipientIds));
        $mocks['notificationRepository']->expects($this->exactly(4))->method('getNotificationsToSend')->will($this->returnValue(array()));
        $mocks['notificationRepository']->expects($this->never())->method('getNotificationsToSendImmediately');
        $mocks['notificationRepository']->expects($this->never())->method('markAllNotificationsAsSentFarInThePast');
        $mocks['notificationRepository']->expects($this->exactly(4))->method('getLastNotificationDate')->will($this->returnValue(new \DateTime('@0')));

        $notifier = new AzineNotifierService($mocks['mailer'], $mocks['twig'], $mocks['router'], $mocks['managerRegistry'], $mocks['templateProvider'], $mocks['recipientProvider'], $mocks['translator'], $mocks['parameters']);
        $sentMails = $notifier->sendNotifications($failedAddresses);
        $this->assertSame(0, sizeof($failedAddresses), 'Email-addresses failed unexpectedly:'.print_r($failedAddresses, true));
        $this->assertSame(4, $sentMails, 'Not the expected number of sent emails.');
    }

    public function testSendNotificationsExampleNotifier()
    {
        $failedAddresses = array();
        $recipientIds = array(11, 12, 13, 14);
        $mocks = $this->getMockSetup();
        $this->mockRecipients($mocks['recipientProvider'], $recipientIds);
        $mocks['mailer']->expects($this->exactly(sizeof($recipientIds)))->method('sendSingleEmail')->will($this->returnCallback(array($this, 'sendSingleEmailCallBack')));

        $notification = new Notification();
        $notification->setContent('bla bla');
        $notification->setCreated(new \DateTime());
        $notification->setImportance(0);
        $notification->setTemplate(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE);
        $notification->setVariables(array('blabla' => 'blablaValue'));
        $notification->setTitle('a title');

        $mocks['notificationRepository']->expects($this->once())->method('getNotificationRecipientIds')->will($this->returnValue($recipientIds));
        $mocks['notificationRepository']->expects($this->exactly(4))->method('getNotificationsToSend')->will($this->returnValue(array($notification)));
        $mocks['notificationRepository']->expects($this->never())->method('getNotificationsToSendImmediately');
        $mocks['notificationRepository']->expects($this->never())->method('markAllNotificationsAsSentFarInThePast');
        $mocks['notificationRepository']->expects($this->exactly(4))->method('getLastNotificationDate')->will($this->returnValue(new \DateTime('@0')));

        $mocks['mailer']->expects($this->exactly(sizeof($recipientIds)))->method('sendSingleEmail');

        $notifier = new ExampleNotifierService($mocks['mailer'], $mocks['twig'], $mocks['router'], $mocks['managerRegistry'], $mocks['templateProvider'], $mocks['recipientProvider'], $mocks['translator'], $mocks['parameters']);
        $sentMails = $notifier->sendNotifications($failedAddresses);
        $this->assertSame(1, sizeof($failedAddresses));
        $this->assertSame(sizeof($recipientIds) - 1, $sentMails);
    }

    private function mockRecipients(\PHPUnit_Framework_MockObject_MockObject $mock, array $ids)
    {
        $notificationType = 0;
        $notificationTypes = array(RecipientInterface::NOTIFICATION_MODE_IMMEDIATELY, RecipientInterface::NOTIFICATION_MODE_HOURLY, RecipientInterface::NOTIFICATION_MODE_DAYLY);
        $valueMap = array();
        foreach ($ids as $id) {
            $recipientMock = $this->getMockBuilder("Azine\EmailBundle\Entity\RecipientInterface")->disableOriginalConstructor()->getMock();
            $recipientMock->expects($this->any())->method('getEmail')->will($this->returnValue($id.'mail@email.com'));
            $recipientMock->expects($this->any())->method('getDisplayName')->will($this->returnValue("DisplayName of $id"));
            $recipientMock->expects($this->any())->method('getPreferredLocale')->will($this->returnValue('en'));
            $recipientMock->expects($this->any())->method('getNotificationMode')->will($this->returnValue($notificationTypes[$notificationType % sizeof($notificationTypes)]));
            ++$notificationType;
            $valueMap[] = array($id, $recipientMock);
        }

        $mock->expects($this->exactly(sizeof($ids)))->method('getRecipient')->will($this->returnValueMap($valueMap));
    }

    public function testProtectedMethods()
    {
        // create service-instance
        $mocks = $this->getMockSetup();

        $recipientIds = array(11, 12, 13, 14);
        $mocks['recipientProvider']->expects($this->once())->method('getNewsletterRecipientIDs')->will($this->returnValue($recipientIds));

        $notifier = new AzineNotifierService($mocks['mailer'], $mocks['twig'], $mocks['router'], $mocks['managerRegistry'], $mocks['templateProvider'], $mocks['recipientProvider'], $mocks['translator'], $mocks['parameters']);

        // access the protected method and execute it
        $returnValue = self::getMethod('getDateTimeOfLastNewsletter')->invokeArgs($notifier, array());
        $this->assertInstanceOf('DateTime', $returnValue);
        $lastDate = new \DateTime('7 days ago');
        $lastDate->setTime(9, 0);
        $this->assertSame($lastDate->getTimestamp(), $returnValue->getTimestamp());

        $returnValue = self::getMethod('getDateTimeOfNextNewsletter')->invokeArgs($notifier, array());
        $this->assertInstanceOf('DateTime', $returnValue);
        $nextDate = new \DateTime('7 days');
        $nextDate->setTime(9, 0);
        $this->assertSame($nextDate->getTimestamp(), $returnValue->getTimestamp());

        $returnValue = self::getMethod('getHourInterval')->invokeArgs($notifier, array());
        $this->assertSame((60 * 60 - 3 * 60), $returnValue);

        $returnValue = self::getMethod('getGeneralVarsForNewsletter')->invokeArgs($notifier, array());
        $this->assertSame(sizeof($recipientIds), $returnValue['recipientCount']);

        $recipientMock = $this->getMockBuilder("Azine\EmailBundle\Entity\RecipientInterface")->disableOriginalConstructor()->getMock();
        $recipientMock->expects($this->any())->method('getId')->will($this->returnValue(11));

        $returnValue = self::getMethod('markAllNotificationsAsSentFarInThePast')->invokeArgs($notifier, array($recipientMock));
    }

    /**
     * @param string $name
     */
    private static function getMethod($name)
    {
        $class = new \ReflectionClass("Azine\EmailBundle\Services\AzineNotifierService");
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}
