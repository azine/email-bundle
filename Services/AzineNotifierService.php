<?php
namespace Azine\EmailBundle\Services;
use Azine\EmailBundle\Entity\Repositories\NotificationRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Entity\Notification;
use Azine\EmailBundle\Entity\RecipientInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This Service compiles and renders the emails to be sent.
 * @author Dominik Businger
 */
class AzineNotifierService implements NotifierServiceInterface
{
    /**
     * Override this function to fill in any non-recipient-specific parameters that are required to
     * render the notifications-template or one of the  notification-item-templates that
     * are rendered into the notifications-template
     *
     * @return array
     */
    protected function getVarsForNotificationsEmail()
    {
        $params = array();

        return $params;
    }

    /**
     * Override this function to fill in any recipient-specific parameters that are required to
     * render the notifications-template or one of the  notification-item-templates that
     * are rendered into the notifications-template
     *
     * @param  RecipientInterface $recipient
     * @return array
     */
    protected function getRecipientVarsForNotificationsEmail(RecipientInterface $recipient)
    {
        $recipientParams = array();
        $recipientParams['recipient'] = $recipient;
        $recipientParams['mode'] = $recipient->getNotificationMode();

        return $recipientParams;
    }

    /**
     * Get the subject for the notifications-email to send. Override this function to implement your custom subject-lines.
     * @param  array of array     $contentItems
     * @param  RecipientInterface $recipient
     * @return string
     */
    public function getRecipientSpecificNotificationsSubject($contentItems, RecipientInterface $recipient)
    {
        $count = sizeof($contentItems);

        if($count == 1){
        	// get the content-item out of the boxed associative array => array(array('templateId' => contentItem))
        	$onlyItem = current(current($contentItems));
        	// get the title out of the notification in the contentItem
        	return $onlyItem['notification']->getTitle();
        }

        return $this->translatorService->transChoice("_az.email.notifications.subject.%count%", $count, array('%count%' => $count));
    }

    /**
     * Override this function to fill in any non-recipient-specific parameters that are required
     * to render the newsletter-template and are not provided by the TemplateProvider. e.g. the total number of recipients of this newsletter
     *
     * @return array
     */
    protected function getGeneralVarsForNewsletter()
    {
        $vars = array();
        $vars['recipientCount'] = sizeof($this->recipientProvider->getNewsletterRecipientIDs());

        return $vars;
    }

    /**
     * Override this function to fill in any non-recipient-specific content items that are the same
     * for all recipients of the newsletter.
     *
     * E.g. a list of featured events or news-articles.
     *
     * @return array of templatesIds (without ending) as key and params to render the template as value. => array('AzineEmailBundle:contentItem:message' => array('notification => $someNotification, 'goToUrl' => 'http://example.com', ...));
     */
    protected function getNonRecipientSpecificNewsletterContentItems()
    {
        // @codeCoverageIgnoreStart
        $contentItems = array();

        //$contentItems[] = array('AcmeBundle:foo:barSameForAllRecipientsTemplate' => $templateParams);
        return $contentItems;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Override this function to add more parameters that are required to render the newsletter template.
     * @param  RecipientInterface           $recipient
     * @return array
     */
    public function getRecipientSpecificNewsletterParams(RecipientInterface $recipient)
    {
        return array('recipient' => $recipient);
    }

    /**
     * Override this function to fill in any recipient-specific content items that are different
     * depending on the recipient of the newsletter.
     *
     * E.g. a list of the recipients latest activites.
     *
     * @param  RecipientInterface $recipient
     * @return array              of arrays with templatesIds (without ending) as key and params to render the template as value.
     *                                      => array(
     *                                      array('AzineEmailBundle:contentItem:message' => array('notification => $someNotification1, 'goToUrl' => 'http://example.com/1', ...))
     *                                      array('AzineEmailBundle:contentItem:message' => array('notification => $someNotification2, 'goToUrl' => 'http://example.com/2', ...))
     *                                      );
     */
    protected function getRecipientSpecificNewsletterContentItems(RecipientInterface $recipient)
    {
        // @codeCoverageIgnoreStart
        $contentItems = array();

        //$contentItems[] = array('AcmeBundle:foo:barDifferentForEachRecipientTemplate' => $recipientSpecificTemplateParams);
        //$contentItems[] = array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('notification' => array('title' => 'SampleMessage', 'created' => new \DateTime('1 hour ago'), 'content' => 'Sample Text. Lorem Ipsum.')));
        return $contentItems;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Override this function to use a custom subject line for each newsletter-recipient.
     *
     * @param $generalContentItems array of content items. => e.g. array of array('templateID' => array('notification => $someNotification, 'goToUrl' => 'http://example.com', ...))
     * @param $recipientContentItems array of content items. => e.g. array of array('templateID' => array('notification => $someNotification, 'goToUrl' => 'http://example.com', ...))
     * @param $params array the array with all general template-params, including the item with the key 'subject' containing the default-subject
     * @param $recipient RecipientInterface
     * @param $locale string The language-code for translation of the subject
     * @return the subject line
     */
    public function getRecipientSpecificNewsletterSubject(array $generalContentItems, array $recipientContentItems, array $params, RecipientInterface $recipient, $locale)
    {
        return $params['subject'];
    }

    /**
     * By overriding this function you can rearrange the content items to you liking. By default no ordering is done, so the order is as follows:
     *
     * - all user-specific content items as returned by AzineNotifierService::getRecipientSpecificNewsletterContentItems
     * - all non-user-specific content items as returned by AzineNotifierService::getNonRecipientSpecificNewsletterContentItems
     *
     * @param array $contentItems
     * @return array
     */
    public function orderContentItems(array $contentItems){
        return $contentItems;
    }

    /**
     * Over ride this constructor if you need to inject more dependencies to get all the data together that you need for your newsletter/notifications.
     *
     * @param TemplateTwigSwiftMailerInterface $mailer
     * @param \Twig_Environment                $twig
     * @param UrlGeneratorInterface            $router
     * @param ManagerRegistry                  $managerRegistry
     * @param TemplateProviderInterface        $templateProvider
     * @param RecipientProviderInterface       $recipientProvider
     * @param Translator                       $translatorService
     * @param array                            $parameters
     */
    public function __construct(TemplateTwigSwiftMailerInterface $mailer, \Twig_Environment $twig, UrlGeneratorInterface $router,
                               ManagerRegistry $managerRegistry, TemplateProviderInterface $templateProvider, RecipientProviderInterface $recipientProvider,
            Translator $translatorService, array $parameters) {

        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->router = $router;
        $this->managerRegistry = $managerRegistry;
        $this->templateProvider = $templateProvider;
        $this->recipientProvider = $recipientProvider;
        $this->translatorService = $translatorService;
        $this->configParameter = $parameters;
    }

    //////////////////////////////////////////////////////////////////////////
    /* You probably don't need to change or override any of the stuff below */
    //////////////////////////////////////////////////////////////////////////

    const CONTENT_ITEMS = 'contentItems';
    /**
     * @var TemplateTwigSwiftMailerInterface
     */
    protected $mailer;

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    /**
     * @var TemplateProviderInterface
     */
    protected $templateProvider;

    /**
     * @var RecipientProviderInterface
     */
    protected $recipientProvider;

    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    /**
     * Array of configuration-parameters from the config.yml
     * @var array
     */
    protected $configParameter;

    /**
     * The translator
     * @var Translator
     */
    protected $translatorService;

    /**
     * Get the number of seconds in a "one-hour-interval"
     * @return integer of seconds to consider as an hour.
     */
    protected function getHourInterval()
    {
        // about an hour ago (57min)
        // this is because if the last run started 60min. ago, then the notifications
        // for any recipient have been send after that and would be skipped until the next run.
        // if your cron-job runs every minute, this is not needed.
        return 	60*60 - 3*60;
    }

    /**
     * Get the number of seconds in a "one-day-interval"
     * @return integer of seconds to consider as a day.
     */
    protected function getDayInterval()
    {
        // about a day ago (23h57min)
        // this is because if the last run started 24h. ago, then the notifications
        // for any recipient have been send after that and would be skipped until the next run.
        // if your cron-job runs every minute, this is not needed.
        return 60 * 60 * 24 - 3 * 60;
    }

    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.NotifierServiceInterface::sendNotifications()
     */
    public function sendNotifications(array &$failedAddresses)
    {
        // get all recipientIds with pending notifications in the database, that are due to be sent
        $recipientIds = $this->getNotificationRecipientIds();

        // get vars that are the same for all recipients of this notification-mail-batch
        $params = $this->getVarsForNotificationsEmail();

        $notificationsTemplate = $this->configParameter[AzineEmailExtension::TEMPLATES."_".AzineEmailExtension::NOTIFICATIONS_TEMPLATE];

        $sentCount = 0;
        foreach ($recipientIds as $recipientId) {

            // send the mail for this recipient
            $failedAddress = $this->sendNotificationsFor($recipientId, $notificationsTemplate, $params);
            if ($failedAddress !== null && strlen($failedAddress) > 0) {
                $failedAddresses[] = $failedAddress;
            } else {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Send the notifications-email for one recipient
     * @param  integer     $recipientId
     * @param  string      $wrapperTemplateName
     * @param  array       $params              array of parameters for this recipient
     * @return null|string or the failed email addressess
     */
    public function sendNotificationsFor($recipientId, $wrapperTemplateName, $params)
    {
        // get the recipient
        $recipient = $this->recipientProvider->getRecipient($recipientId);

        // get all Notification-Items for the recipient from the database
        $notifications = $this->getNotificationsFor($recipient);
        if (sizeof($notifications) == 0 ) {
            return null;
        }

        // get the recipient specific parameters for the twig-templates
        $recipientParams = $this->getRecipientVarsForNotificationsEmail($recipient);
        $params = array_merge($recipientParams, $params);

        // prepare the arrays with template and template-variables for each notification
        $contentItems = array();
        foreach ($notifications as $notification) {

            // decode the $params from the json in the notification-entity
            $itemVars = $notification->getVariables();
            $itemVars = array_merge($params, $itemVars);
            $itemVars['notification'] = $notification;
            $itemVars['recipient'] = $recipient;

            $itemTemplateName = $notification->getTemplate();

            $contentItems[] = array($itemTemplateName => $itemVars);
        }

        // add the notifications to the params array so they will be rendered later
        $params[self::CONTENT_ITEMS] = $contentItems;
        $params['recipient'] = $recipient;
        $params['_locale'] = $recipient->getPreferredLocale();

        $subject = $this->getRecipientSpecificNotificationsSubject($contentItems, $recipient);

        // send the email with the right wrapper-template
        $sent = $this->mailer->sendSingleEmail($recipient->getEmail(), $recipient->getDisplayName(), $subject, $params, $wrapperTemplateName . ".txt.twig", $recipient->getPreferredLocale());

        if ($sent) {
            // save the updated notifications
            $this->setNotificationsAsSent($notifications);
            return null;

        } else {
           return $recipient->getEmail();
        }
    }

    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.NotifierServiceInterface::sendNewsletter()
     */
    public function sendNewsletter(array &$failedAddresses)
    {
        // params array for all recipients
        $params = array();

        // set a default subject
        $params['subject'] = $this->translatorService->trans("_az.email.newsletter.subject");

        // get the the non-recipient-specific contentItems of the newsletter
        $params[self::CONTENT_ITEMS] = $this->getNonRecipientSpecificNewsletterContentItems();

        // get recipientIds for the newsletter
        $recipientIds = $this->recipientProvider->getNewsletterRecipientIDs();

        $newsletterTemplate = $this->configParameter[AzineEmailExtension::TEMPLATES."_".AzineEmailExtension::NEWSLETTER_TEMPLATE];

        foreach ($recipientIds as $recipientId) {
            $failedAddress = $this->sendNewsletterFor($recipientId, $params, $newsletterTemplate);

            if ($failedAddress !== null && strlen($failedAddress) > 0) {
                $failedAddresses[] = $failedAddress;
            }
        }

        return sizeof($recipientIds) - sizeof($failedAddresses);
    }

    /**
     * Send the newsletter for one recipient
     * @param  integer     $recipientId
     * @param  array       $params          params and contentItems that are the same for all recipients
     * @param  string      $wrapperTemplate
     * @return string|null or the failed email addressess
     */
    public function sendNewsletterFor($recipientId, array $params, $wrapperTemplate)
    {
        $recipient = $this->recipientProvider->getRecipient($recipientId);

        // create new array for each recipient.
        $recipientParams = array_merge($params, $this->getRecipientSpecificNewsletterParams($recipient));

        // get the recipient-specific contentItems of the newsletter
        $recipientContentItems = $this->getRecipientSpecificNewsletterContentItems($recipient);

        // merge the recipient-specific and the general content items. recipient-specific first/at the top!
        $recipientParams[self::CONTENT_ITEMS] = $this->orderContentItems(array_merge($recipientContentItems,  $params[self::CONTENT_ITEMS]));
        $recipientParams['_locale'] = $recipient->getPreferredLocale();

        if (sizeof($recipientParams[self::CONTENT_ITEMS]) == 0) {
            return $recipient->getEmail();
        }

        $subject = $this->getRecipientSpecificNewsletterSubject($params[self::CONTENT_ITEMS], $recipientContentItems, $params, $recipient, $recipient->getPreferredLocale());

        // render and send the email with the right wrapper-template
        $sent = $this->mailer->sendSingleEmail($recipient->getEmail(), $recipient->getDisplayName(), $subject, $recipientParams, $wrapperTemplate.".txt.twig", $recipient->getPreferredLocale());

        if ($sent) {
            // save that this recipient has recieved the newsletter
            return null;

        } else {
            return $recipient->getEmail();
        }

    }

    /**
     * Get the Notifications that have not yet been sent yet.
     * Ordered by "template" and "title".
     * @param  RecipientInterface $recipient
     * @return array              of Notification
     */
    protected function getNotificationsFor(RecipientInterface $recipient)
    {
        // get the notification mode
        $notificationMode = $recipient->getNotificationMode();

        // get the date/time of the last notification
        $lastNotificationDate = $this->getNotificationRepository()->getLastNotificationDate($recipient->getId());

        $sendNotifications = false;
        $timeDelta = time() - $lastNotificationDate->getTimestamp();

        if ($notificationMode == RecipientInterface::NOTIFICATION_MODE_IMMEDIATELY) {
            $sendNotifications = true;

        } elseif ($notificationMode == RecipientInterface::NOTIFICATION_MODE_HOURLY) {
            $sendNotifications = ($timeDelta > $this->getHourInterval());

        } elseif ($notificationMode == RecipientInterface::NOTIFICATION_MODE_DAYLY) {
            $sendNotifications = ($timeDelta > $this->getDayInterval());

        } elseif ($notificationMode == RecipientInterface::NOTIFICATION_MODE_NEVER) {
            $this->markAllNotificationsAsSentFarInThePast($recipient);

            return array();
        }

        // regularly sent notifications now
        if ($sendNotifications) {
            $notifications = $this->getNotificationRepository()->getNotificationsToSend($recipient->getId());

        // if notifications exist, that should be sent immediately, then send those now disregarding the users mailing-preferences.
        } else {
            $notifications = $this->getNotificationRepository()->getNotificationsToSendImmediately($recipient->getId());
        }

        return $notifications;
    }

    /**
     * Get all IDs for Recipients of pending notifications
     * @return array of IDs
     */
    protected function getNotificationRecipientIds()
    {
        return $this->getNotificationRepository()->getNotificationRecipientIds();
    }

    /**
     * Update (set sent = now) and save the notifications
     * @param array $notifications
     */
    protected function setNotificationsAsSent(array $notifications)
    {
        foreach ($notifications as $notification) {
            $notification->setSent(new \DateTime());
            $this->managerRegistry->getManager()->persist($notification);
        }
        $this->managerRegistry->getManager()->flush();
    }

    /**
     * Mark all Notifications as sent long ago, as the recipient never want's to get any notifications.
     * @param RecipientInterface $recipient
     */
    protected function markAllNotificationsAsSentFarInThePast(RecipientInterface $recipient)
    {
        $this->getNotificationRepository()->markAllNotificationsAsSentFarInThePast($recipient->getId());
    }

    /**
     * Get the interval in days between newsletter mailings
     */
    protected function getNewsletterInterval()
    {
        return $this->configParameter[AzineEmailExtension::NEWSLETTER."_".AzineEmailExtension::NEWSLETTER_INTERVAL];
    }

    /**
     * Get the time of the day when the newsletter should be sent.
     * @return string Time of the day in the format HH:mm
     */
    protected function getNewsletterSendTime()
    {
        return $this->configParameter[AzineEmailExtension::NEWSLETTER."_".AzineEmailExtension::NEWSLETTER_SEND_TIME];
    }

    /**
     * Get the DateTime at which the last newsletter mailing probably has taken place, if a newsletter is sent today.
     * (Calculated: send-time-today - interval in days)
     * @return \DateTime
     */
    protected function getDateTimeOfLastNewsletter()
    {
        return new \DateTime($this->getNewsletterInterval()." days ago ".$this->getNewsletterSendTime());
    }

    /**
     * Get the DateTime at which the next newsletter mailing will take place, if a newsletter is sent today.
     * (Calculated: send-time-today + interval in days)
     */
    protected function getDateTimeOfNextNewsletter()
    {
        return new \DateTime("+".$this->getNewsletterInterval()." days  ".$this->getNewsletterSendTime());
    }

    /**
     * Convenience-function to add and save a Notification-entity
     *
     * @param  integer      $recipientId     the ID of the recipient of this notification => see RecipientProvider.getRecipient($id)
     * @param  string       $title           the title of the notification. depending on the recipients settings, multiple notifications are sent in one email.
     * @param  string       $content         the content of the notification
     * @param  string       $template        the twig-template to render the notification with
     * @param  array        $templateVars    the parameters used in the twig-template, 'notification' => Notification and 'recipient' => RecipientInterface will be added to this array when rendering the twig-template.
     * @param  integer      $importance      important messages are at the top of the notification-emails, un-important at the bottom.
     * @param  boolean      $sendImmediately whether or not to ignore the recipients mailing-preference and send the notification a.s.a.p.
     * @return Notification
     */
    public function addNotification($recipientId, $title, $content, $template, $templateVars, $importance, $sendImmediately)
    {
        $notification = new Notification();
        $notification->setRecipientId($recipientId);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setTemplate($template);
        $notification->setImportance($importance);
        $notification->setSendImmediately($sendImmediately);
        $notification->setVariables($templateVars);
        $this->managerRegistry->getManager()->persist($notification);
        $this->managerRegistry->getManager()->flush($notification);

        return $notification;
    }

    /**
     * Convenience-function to add and save a Notification-entity for a message => see AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TYPE
     *
     * The following default are used:
     * $importance		= NORMAL
     * $sendImmediately	= fale
     * $template		= template for type AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TYPE
     * $templateVars	= only those from the template-provider
     *
     * @param integer $recipientId
     * @param string  $title
     * @param string  $content     nl2br will be applied in the html-version of the email
     * @param string  $goToUrl     if this is supplied, a link "Go to message" will be added.
     */
    public function addNotificationMessage($recipientId, $title, $content, $goToUrl = null)
    {
        $contentItemTemplate = $this->configParameter[AzineEmailExtension::TEMPLATES."_".AzineEmailExtension::CONTENT_ITEM_TEMPLATE];
        $templateVars = array();
        if ($goToUrl !== null && strlen($goToUrl) > 0) {
            $templateVars['goToUrl'] = $goToUrl;
        }
        $this->addNotification($recipientId, $title, $content, $contentItemTemplate, $this->templateProvider->addTemplateVariablesFor($contentItemTemplate, $templateVars), Notification::IMPORTANCE_NORMAL, false);
    }


    /**
     * @return NotificationRepository
     */
    protected function getNotificationRepository(){
        return $this->managerRegistry->getRepository('AzineEmailBundle:Notification');
    }

}
