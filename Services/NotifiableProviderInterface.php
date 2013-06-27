<?php

namespace Azine\EmailBundle\Services;

/**
 * Interface with methods required by the AzineEmailBundle to send Notification/Newsletters via email
 * Azine\EmailBundle\Entity\NotifiableProviderInterface
 */
interface NotifiableProviderInterface {

	/**
	 * @param NotifiableInterface $id
	 */
	public function getNotifiableEntity($id);
}