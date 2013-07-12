<?php
namespace Azine\EmailBundle\Services;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;

use Azine\EmailBundle\Entity\Notification;

use Azine\EmailBundle\Entity\RecipientInterface;

use Doctrine\ORM\EntityManager;

use Monolog\Logger;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This Service compiles and renders the emails to be sent.
 * @author Dominik Businger
 */
class AzineNotifierService implements NotifierServiceInterface {

	/**
	 * Override this function to fill in any non-recipient-specific parameters that are required to
	 * render the notifications-template or one of the  notification-item-templates that
	 * are rendered into the notifications-template
	 *
	 * @return array
	 */
	protected function getVarsForNotificationsEmail(){
		$params = array();
		return $params;
	}

	/**
	 * Override this function to fill in any recipient-specific parameters that are required to
	 * render the notifications-template or one of the  notification-item-templates that
	 * are rendered into the notifications-template
	 *
	 * @param RecipientInterface $recipient
	 * @return array
	 */
	protected function getRecipientVarsForNotificationsEmail(RecipientInterface $recipient){
		$recipientParams = array();
		$recipientParams['recipient'] = $recipient;
		$recipientParams['mode'] = $recipient->getNotificationMode();
		$recipientParams['modeAsText'] = $recipient->getNotificationModeAsText();
		return $recipientParams;
	}

	/**
	 * Override this function to fill in any non-recipient-specific parameters that are required
	 * to render the newsletter-template and are not provided by the TemplateProvider. e.g. the total number of recipients of this newsletter
	 *
	 * @return array
	 */
	protected function getGeneralVarsForNewsletter(){
		$vars = array();
		$vars['recipientCount'] = sizeof($this->recipientProvider->getNewsletterRecipientIDs());
	}

	/**
	 * Override this function to fill in any non-recipient-specific content items that are the same
	 * for all recipients of the newsletter.
	 *
	 * E.g. a list of featured events or news-articles.
	 *
	 * @return array of templatesIds (without ending) as key and params to render the template as value. => array('AzineEmailBundle:contentItem:message',array('notification => $someNotification, 'goToUrl' => 'http://example.com', ...));
	 */
	protected function getNonRecipientSpecificNewsletterContentItems(){
		$contentItems = array();

		//$contentItems[] = array('AcmeBundle:foo:barSameForAllRecipientsTemplate', $templateParams);

		return $contentItems;
	}

	/**
	 * Override this function to fill in any recipient-specific content items that are different
	 * depending on the recipient of the newsletter.
	 *
	 * E.g. a list of the recipients latest activites.
	 *
	 * @return array of templatesIds (without ending) as key and params to render the template as value. => array('AzineEmailBundle:contentItem:message',array('notification => $someNotification, 'goToUrl' => 'http://example.com', ...));
	 */
	protected function getRecipientSpecificNewsletterContentItems(RecipientInterface $recipient){
		$contentItems = array();

		//$contentItems[] = array('AcmeBundle:foo:barDifferentForEachRecipientTemplate', $recipientSpecificTemplateParams);

		return $contentItems;
	}

	/**
	 * Get the number of seconds in a "one-hour-interval"
	 * @return number of seconds to consider as an hour.
	 */
	protected function getHourInterval(){
		// about an hour ago (57min)
		// this is because if the last run started 60min. ago, then the notifications
		// for any recipient have been send after that and would be skipped until the next run.
		// if your cron-job runs every minute, this is not needed.
		return 	60*60 - 3*60;
	}

	/**
	 * Get the number of seconds in a "one-day-interval"
	 * @return number of seconds to consider as a day.
	 */
	protected function getDayInterval(){
		// about a day ago (23h57min)
		// this is because if the last run started 24h. ago, then the notifications
		// for any recipient have been send after that and would be skipped until the next run.
		// if your cron-job runs every minute, this is not needed.
		return 60*60*24 - 3*60;
	}

	/**
	 * Over ride this constructor if you need to inject more dependencies to get all the data together that you need for your newsletter/notifications.
	 *
	 * @param TemplateTwigSwiftMailerInterface $mailer
	 * @param \Twig_Environment $twig
	 * @param Logger $logger
	 * @param TemplateProviderInterface $templateProvider
	 * @param RecipientProviderInterface $recipientProvider
	 * @param array $parameters
	 */
	public function __construct(	TemplateTwigSwiftMailerInterface $mailer,
									\Twig_Environment $twig,
									Logger $logger,
									UrlGeneratorInterface $router,
									EntityManager $entityManager,
									TemplateProviderInterface $templateProvider,
									RecipientProviderInterface $recipientProvider,
									array $parameters
								){

		$this->mailer = $mailer;
		$this->twig = $twig;
		$this->logger = $logger;
		$this->router = $router;
		$this->em = $entityManager;
		$this->templateProvider = $templateProvider;
		$this->recipientProvider = $recipientProvider;
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
	 * @var Logger
	 */
	protected $logger;

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
	 * @var EntityManager
	 */
	protected $em;

	/**
	 * Array of TwigTemplates that have been loaded already
	 * @var array
	 */
	private $templateStore = array();

	/**
	 * Array of configuration-parameters from the config.yml
	 * @var array
	 */
	protected $configParameter;

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.NotifierServiceInterface::sendNotifications()
	 */
	public function sendNotifications(array &$failedAddresses){
		// get all recipientIds with pending notifications in the database, that are due to be sent
		$recipientIds = $this->getNotificationRecipientIds();

		// get the template to be used to wrap arround the notifications.
		$wrapperTemplate = $this->templateProvider->getTemplateFor(TemplateProviderInterface::NOTIFICATION_TYPE);

		// get vars that are the same for all recipients of this notification-mail-batch
		$params = $this->getVarsForNotificationsEmail();


		foreach ($recipientIds as $recipientId){

			// send the mail for this recipient
			$failedAddress = $this->sendNotificationsFor($recipientId, $wrapperTemplate, $params);

			if($failedAddress != null){
				$failedAddresses[] = $failedAddress;
			}
		}

		// log mail-errors as warnings
		if(sizeof($failedAddresses) > 0){
			$this->logger->warn("Failed to send message to :\n".print_r($failedAddresses, true));
		}
		return sizeof($recipientIds) - sizeof($failedAddresses);
	}

	/**
	 * Send the notifications-email for one recipient
	 * @param integer $recipientId
	 * @param string $wrapperTemplateName
	 * @param array $params array of parameters for this recipient
	 * @return null or the failed email addressess
	 */
	public function sendNotificationsFor($recipientId, $wrapperTemplateName, $params){
		// get the recipient
		$recipient = $this->recipientProvider->getRecipient($recipientId);

		// get all Notification-Items for the recipient from the database
		$notifications = $this->getNotificationsFor($recipient);
		if(sizeof($notifications) == 0 ) {
			return null;
		}

		// get the recipient specific parameters for the twig-templates
		$recipientParams = $this->getRecipientVarsForNotificationsEmail($recipient);

		// prepare the arrays with template and template-variables for each notification
		$contentItems = array();
		foreach ($notifications as $notification){

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

		// send the email with the right wrapper-template
		$sent = $this->mailer->sendSingleEmail($recipient->getEmail(), $recipient->getDisplayName(), $params, $wrapperTemplateName.".txt.twig", $recipient->getPreferredLocale());

		if($sent){
			// save the updated notifications
			$this->setNotificationsAsSent($notifications);
			return null;

		} else {
			$this->logger->error("The notification for ".$recipient->getDisplayName()." <".$recipient->getEmail()."> could not be sent!", $params);
			return $recipient->getEmail();

		}
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.NotifierServiceInterface::sendNewsletter()
	 */
	public function sendNewsletter(array &$failedAddresses){

		// get the wrapper-template and its variables
		$wrapperTemplate = $this->templateProvider->getTemplateFor(TemplateProviderInterface::NEWSLETTER_TYPE);

		// get the the non-recipient-specific contentItems of the newsletter
		$params[self::CONTENT_ITEMS] = $this->getNonRecipientSpecificNewsletterContentItems( array());

		// get recipientIds for the newsletter
		$recipientIds = $this->recipientProvider->getNewsletterRecipientIDs();

		foreach ($recipientIds as $recipientId){
			$failedAddress = $this->sendNewsletterFor($recipientId, $params, $wrapperTemplate);

			if($failedAddress != null){
				$failedAddresses[] = $failedAddress;
			}
		}

		return sizeof($recipientIds) - sizeof($failedAddresses);
	}

	/**
	 * Send the newsletter for one recipient
	 * @param integer $recipientId
	 * @param array $params params and contentItems that are the same for all recipients
	 * @param string $wrapperTemplate
	 * @return null or the failed email addressess
	 */
	public function sendNewsletterFor($recipientId, array $params, $wrapperTemplate){
		$recipient = $this->recipientProvider->getRecipient($recipientId);

		// create new array for each recipient.
		$recipientParams = array_merge($params, array('recipient' => $recipient));

		// get the recipient-specific contentItems of the newsletter
		$recipientContentItems = $this->getRecipientSpecificNewsletterContentItems($recipient);

		// append the non-recipient-specific block to the recipient-specific blocks => personal stuff first.
		if(array_key_exists(self::CONTENT_ITEMS, $recipientParams)){
			$generalContentItems = $recipientParams[self::CONTENT_ITEMS];
		} else {
			$generalContentItems = array();
		}
		$recipientParams[self::CONTENT_ITEMS] = array_merge($recipientContentItems, $generalContentItems);
		$recipientParams['_locale'] = $recipient->getPreferredLocale();


		// render and send the email with the right wrapper-template
		$sent = $this->mailer->sendSingleEmail($recipient->getEmail(), $recipient->getDisplayName(), $recipientParams, $wrapperTemplate.".txt.twig", $recipient->getPreferredLocale());

		if($sent){
			// save that this recipient has recieved the newsletter
			//$this->setNotificationsAsSent($notifications);
			return null;

		} else {
			$this->logger->error("The newsletter for ".$recipient->getDisplayName()." <".$recipient->getEmail()."> could not be sent!", $params);
			return $recipient->getEmail();

		}
	}

	/**
	 * Get the Notifications that have not yet been sent yet.
	 * Ordered by "template" and "title".
	 * @param RecipientInterface $recipient
	 * @return array of Notification
	 */
	protected function getNotificationsFor(RecipientInterface $recipient){
		// get the notification mode
		$notificationMode = $recipient->getNotificationMode();

		// get the date/time of the last notification
		$qb = $this->em->createQueryBuilder()
			->select("max(n.sent)")
			->from("Azine\EmailBundle\Entity\Notification", "n")
			->andWhere("n.recipient_id = :recipientId")
			->setParameter('recipientId', $recipient->getId());
		$results = $qb->getQuery()->execute();
		if($results[0][1] == null){
			$lastNotification = new \DateTime("@0");
		} else {
			$lastNotification = new \DateTime($results[0][1]);
		}

		$sendNotifications = false;
		$timeDelta = time() - $lastNotification->getTimestamp();

		if($notificationMode == RecipientInterface::NOTIFICATION_MODE_IMMEDIATELY){
			$sendNotifications = true;

		} else if($notificationMode == RecipientInterface::NOTIFICATION_MODE_HOURLY){
			$sendNotifications = ($timeDelta > $this->getHourInterval());

		} else if($notificationMode == RecipientInterface::NOTIFICATION_MODE_DAYLY){
			$sendNotifications = ($timeDelta > $this->getDayInterval());


		} else if($notificationMode == RecipientInterface::NOTIFICATION_MODE_NEVER){
			$sendNotifications = false;
			$this->markAllNotificationsAsSentFarInThePast($recipient);
			return array();
		}

		$qb = $this->em->createQueryBuilder()
			->select("n")
			->from("Azine\EmailBundle\Entity\Notification", "n")
			->andWhere("n.sent is null")
			->andWhere("n.recipient_id = :recipientId")
			->setParameter('recipientId', $recipient->getId())
			->orderBy("n.template", "asc")
			->orderBy("n.title", "asc");
		$results = $qb->getQuery()->execute();
		return $results;
	}

	/**
	 * Get all IDs for Recipients of pending notifications
	 * @return array of IDs
	 */
	protected function getNotificationRecipientIds(){
		$qb = $this->em->createQueryBuilder()
			->select("n.recipient_id")
			->distinct()
			->from("Azine\EmailBundle\Entity\Notification", "n")
			->andWhere("n.sent is null");
		$results = $qb->getQuery()->execute();

		$ids = array();
		foreach ($results as $next){
			$ids[] = $next['recipient_id'];
		}
		return $ids;
	}

	/**
	 * Update (set sent = now) and save the notifications
	 * @param array $notifications
	 */
	protected function setNotificationsAsSent(array $notifications){
		foreach ($notifications as $notification){
			//$notification->setSent(new \DateTime());
			$this->em->persist($notification);
		}
		$this->em->flush();
	}

	/**
	 * @param string $name
	 * @param string $format
	 * @var \Twig_Template
	 */
	protected function getTwigTemplate($templateId){
		if(!array_key_exists($templateId, $this->templateStore)){
			$this->templateStore[$templateId] = $this->twig->loadTemplate($templateId);
		}
		return $this->templateStore[$templateId];
	}

	/**
	 * Mark all Notifications as sent long ago, as the recipient never want's to get any notifications.
	 * @param RecipientInterface $recipient
	 */
	protected function markAllNotificationsAsSentFarInThePast(RecipientInterface $recipient){
		$this->em->createQueryBuilder()
			->update("Azine\EmailBundle\Entity\Notification", "n")
			->set("sent", new \DateTime('1900-01-01'))
			->andWhere("n.sent is null")
			->andWhere("n.recipient_id = :recipientId")
			->setParameter('recipientId', $recipient->getId());
		$qb->getQuery()->execute();
	}

	/**
	 * Get the interval in days between newsletter mailings
	 */
	protected function getNewsletterInterval(){
		return $this->configParameter[AzineEmailExtension::NEWSLETTER_INTERVAL];
	}

	/**
	 * Get the time of the day when the newsletter should be sent.
	 */
	protected function getNewsletterSendTime(){
		return $this->configParameter[AzineEmailExtension::NEWSLETTER_SEND_TIME];
	}

	/**
	 * Get the DateTime at which the last newsletter mailing probably has taken place. (Calculated: send-time-today - interval in days)
	 */
	protected function getDateTimeOfLastNewsletter(){
		return new \DateTime($this->getNewsletterInterval()." days ago ".$this->getNewsletterSendTime());
	}

}
