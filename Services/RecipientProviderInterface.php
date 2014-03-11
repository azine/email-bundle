<?php
namespace Azine\EmailBundle\Services;

use Azine\EmailBundle\Entity\RecipientInterface;

/**
 * Interface with methods required by the AzineEmailBundle to send Notification/Newsletters via email
 *
 * @author dominik
 */
interface RecipientProviderInterface
{
    /**
     * Get the recipient entity with the given id.
     * @param  integer            $id
     * @return RecipientInterface
     */
    public function getRecipient($id);

    /**
     * Get all recipient entities that like to recieve the newsletter.
     * @return array of notibiable entity IDs
     */
    public function getNewsletterRecipientIDs();
}
