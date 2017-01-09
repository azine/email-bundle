<?php

namespace Azine\EmailBundle\Entity;

use FOS\UserBundle\Model\User;

/**
 * ExampleUser
 * This class is only an example. Implement your own!
 * @codeCoverageIgnore
 */
class ExampleUser extends User implements RecipientInterface {

    /**
     * @var integer
     */
    protected $id;

    /**
     * Set some defaults
     */
    public function __construct()
    {
        parent::__construct();
        $this->setNotificationMode(RecipientInterface::NOTIFICATION_MODE_IMMEDIATELY);
        $this->setNewsletter(true);
    }

    /**
     * @return string representation of this user
     */
    public function getDisplayName()
    {
        $firstName = $this->getFirstName();
        $lastName = $this->getLastName();
        $username = $this->getUsername();

        $displayName = $username;
        if ($firstName) {
            $displayName = $firstName;
        } elseif ($lastName) {
            $displayName = $this->getSalutation()." ".$lastName;
        }

        return ucwords($displayName);
    }



///////////////////////////////////////////////////////////////////
// generated stuff only below this line.
// @codeCoverageIgnoreStart
///////////////////////////////////////////////////////////////////

    /**
     * @var string
     */
    private $first_name;

    /**
     * @var string
     */
    private $last_name;

    /**
     * @var string
     */
    private $salutation;

    /**
     * @var string
     */
    private $preferredLocale;

    /**
     * @var integer
     */
    private $notification_mode;

    /**
     * @var boolean
     */
    private $newsletter;

    /**
     * Set first_name
     *
     * @param string $firstName
     * @return ExampleUser
     */
    public function setFirstName($firstName)
    {
        $this->first_name = $firstName;

        return $this;
    }

    /**
     * Get first_name
     *
     * @return string 
     */
    public function getFirstName()
    {
        return $this->first_name;
    }

    /**
     * Set last_name
     *
     * @param string $lastName
     * @return ExampleUser
     */
    public function setLastName($lastName)
    {
        $this->last_name = $lastName;

        return $this;
    }

    /**
     * Get last_name
     *
     * @return string 
     */
    public function getLastName()
    {
        return $this->last_name;
    }

    /**
     * Set salutation
     *
     * @param string $salutation
     * @return ExampleUser
     */
    public function setSalutation($salutation)
    {
        $this->salutation = $salutation;

        return $this;
    }

    /**
     * Get salutation
     *
     * @return string 
     */
    public function getSalutation()
    {
        return $this->salutation;
    }

    /**
     * Set preferredLocale
     *
     * @param string $preferredLocale
     * @return ExampleUser
     */
    public function setPreferredLocale($preferredLocale)
    {
        $this->preferredLocale = $preferredLocale;

        return $this;
    }

    /**
     * Get preferredLocale
     *
     * @return string 
     */
    public function getPreferredLocale()
    {
        return $this->preferredLocale;
    }

    /**
     * Set notification_mode
     *
     * @param integer $notificationMode
     * @return ExampleUser
     */
    public function setNotificationMode($notificationMode)
    {
        $this->notification_mode = $notificationMode;

        return $this;
    }

    /**
     * Get notification_mode
     *
     * @return integer 
     */
    public function getNotificationMode()
    {
        return $this->notification_mode;
    }

    /**
     * Set newsletter
     *
     * @param boolean $newsletter
     * @return ExampleUser
     */
    public function setNewsletter($newsletter)
    {
        $this->newsletter = $newsletter;

        return $this;
    }

    /**
     * Get newsletter
     *
     * @return boolean 
     */
    public function getNewsletter()
    {
        return $this->newsletter;
    }
}
