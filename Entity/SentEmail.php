<?php

namespace Azine\EmailBundle\Entity;

/**
 * SentEmail
 */
class SentEmail
{
    /**
     * Generate a 23-char unique id
     * @return string
     */
    public static function getNewToken()
    {
        return uniqid("", true);
    }

///////////////////////////////////////////////////////////////////
// generated stuff only below this line.
// @codeCoverageIgnoreStart
///////////////////////////////////////////////////////////////////

    /**
     * @var integer
     */
    private $id;

    /**
     * @var array
     */
    private $recipients;

    /**
     * @var string
     */
    private $template;

    /**
     * @var array
     */
    private $variables;

    /**
     * @var \DateTime
     */
    private $sent;

    /**
     * @var string
     */
    private $token;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set recipients
     *
     * @param  array     $recipients
     * @return SentEmail
     */
    public function setRecipients($recipients)
    {
        $this->recipients = $recipients;

        return $this;
    }

    /**
     * Get recipients
     *
     * @return array
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * Set template
     *
     * @param  string    $template
     * @return SentEmail
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Get template
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set variables
     *
     * @param  array     $variables
     * @return SentEmail
     */
    public function setVariables($variables)
    {
        $this->variables = $variables;

        return $this;
    }

    /**
     * Get variables
     *
     * @return array
     */
    public function getVariables()
    {
        return $this->variables;
    }

    /**
     * Set sent
     *
     * @param  \DateTime $sent
     * @return SentEmail
     */
    public function setSent($sent)
    {
        $this->sent = $sent;

        return $this;
    }

    /**
     * Get sent
     *
     * @return \DateTime
     */
    public function getSent()
    {
        return $this->sent;
    }

    /**
     * Set token
     *
     * @param  string    $token
     * @return SentEmail
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Get token
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }
}
