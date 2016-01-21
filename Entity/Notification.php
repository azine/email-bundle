<?php

namespace Azine\EmailBundle\Entity;

/**
 * Notification
 */
class Notification
{
    const IMPORTANCE_LOW = 1;
    const IMPORTANCE_NORMAL = 2;
    const IMPORTANCE_HIGH = 3;
    /**
     * Initialize the created-date with "now"
     */
    public function setCreatedValue()
    {
        $this->created = new \DateTime();
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
     * @var integer
     */
    private $recipient_id;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $content;

    /**
     * @var string
     */
    private $template;

    /**
     * @var boolean
     */
    private $send_immediately;

    /**
     * @var integer
     */
    private $importance;

    /**
     * @var \DateTime
     */
    private $sent;

    /**
     * @var \DateTime
     */
    private $created;

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
     * Set recipient_id
     *
     * @param  integer      $recipientId
     * @return Notification
     */
    public function setRecipientId($recipientId)
    {
        $this->recipient_id = $recipientId;

        return $this;
    }

    /**
     * Get recipient_id
     *
     * @return integer
     */
    public function getRecipientId()
    {
        return $this->recipient_id;
    }

    /**
     * Set title
     *
     * @param  string       $title
     * @return Notification
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set content
     *
     * @param  string       $content
     * @return Notification
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set template
     *
     * @param  string       $template
     * @return Notification
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
     * Set send_immediately
     *
     * @param  boolean      $sendImmediately
     * @return Notification
     */
    public function setSendImmediately($sendImmediately)
    {
        $this->send_immediately = $sendImmediately;

        return $this;
    }

    /**
     * Get send_immediately
     *
     * @return boolean
     */
    public function getSendImmediately()
    {
        return $this->send_immediately;
    }

    /**
     * Set importance
     *
     * @param  integer      $importance
     * @return Notification
     */
    public function setImportance($importance)
    {
        $this->importance = $importance;

        return $this;
    }

    /**
     * Get importance
     *
     * @return integer
     */
    public function getImportance()
    {
        return $this->importance;
    }

    /**
     * Set sent
     *
     * @param  \DateTime    $sent
     * @return Notification
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
     * Set created
     *
     * @param  \DateTime    $created
     * @return Notification
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }
    /**
     * @var array
     */
    private $variables;

    /**
     * Set variables
     *
     * @param  array        $variables
     * @return Notification
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
}
