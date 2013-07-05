<?php
namespace Azine\EmailBundle\Services;

use Azine\EmailBundle\Entity\RecipientInterface;

/**
 * This Service compiles and renders the emails to be sent.
 * @author Dominik Businger
 */
class ExampleNotifierService extends AzineNotifierService {

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.AzineNotifierService::getRecipientVarsForNotificationsEmail()
	 */
	protected function getRecipientVarsForNotificationsEmail(RecipientInterface $recipient){
		$recipientParams = parent::getRecipientVarsForNotificationsEmail($recipient);
		return $recipientParams;
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.AzineNotifierService::getNonRecipientSpecificNewsletterContentItems()
	 */
	protected function getNonRecipientSpecificNewsletterContentItems(){
		$contentItems = array();

		//$contentItems[] = array('AcmeBundle:foo:barSameForAllRecipientsTemplate', $templateParams);

		return $contentItems;
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.AzineNotifierService::getRecipientSpecificNewsletterContentItems()
	 */
	protected function getRecipientSpecificNewsletterContentItems(RecipientInterface $recipient){
		$contentItems = array();

		//$contentItems[] = array('AcmeBundle:foo:barDifferentForEachRecipientTemplate', $recipientSpecificTemplateParams);

		return $contentItems;
	}


}
