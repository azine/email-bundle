<?php

namespace Azine\EmailBundle\Entity;

/**
 * Interface with methods required by the AzineEmailBundle to send Notification/Newsletters via email
 * Azine\EmailBundle\Entity\RecipientInterface
 */
interface RecipientInterface
{
    const NOTIFICATION_MODE_NEVER = 0;
    const NOTIFICATION_MODE_DAYLY = 1;
    const NOTIFICATION_MODE_HOURLY = 2;
    const NOTIFICATION_MODE_IMMEDIATELY = 3;

    /**
     * Get the unique id of this entity (e.g.e the userId)
     * @return integer
     */
    public function getId();

    /**
     * Get the recipients email address
     * @return string email address
     */
    public function getEmail();

    /**
     * Get the recipients Name (e.g. "Mr. John Doe")
     * @return string
     */
    public function getDisplayName();

    /**
     * Get the interval for notifications
     * @return integer
     */
    public function getNotificationMode();

    /**
     * Whether the recipient likes to get the newsletter or not
     * @return boolean
     */
    public function getNewsletter();

    /**
     * Get the recipients prefered locale for the emails
     * @return string
     */
    public function getPreferredLocale();

}
