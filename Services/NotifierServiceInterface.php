<?php
namespace Azine\EmailBundle\Services;

/**
 * Interface with methods required by the AzineEmailBundle to send Notification/Newsletters via email
 */
interface NotifierServiceInterface
{
    /**
     * Send all pending notifications to their recipients
     *
     * @param $failedAddresses array modified by reference, so after the function returns, the array contains the failed email-addresses.
     * @return array of failed email addressess
     */
    public function sendNotifications(array &$failedAddresses);

    /**
     * Send the Newsletter to all recipients.
     *
     * @param $failedAddresses array modified by reference, so after the function returns, the array contains the failed email-addresses.
     * @return array of failed email addressess
     */
    public function sendNewsletter(array &$failedAddresses);

}
