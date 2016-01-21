<?php
namespace Azine\EmailBundle\Services;

use Doctrine\ORM\EntityManager;

/**
 * Default implementation of the RecipientProviderInterface
 */
 class AzineRecipientProvider implements RecipientProviderInterface
 {
    /** @var EntityManager */
    private $em;

    /** @var string your recipient class */
    private $userClass;

    /** @var string the field name of the boolean field that indicate wether or not a newsletter should be sent to the recipient entity. */
    private $newsletterField;

    /**
     *
     * @param EntityManager $em
     * @param string        $userClass
     * @param string        $newsletterField
     */
    public function __construct(EntityManager $em, $userClass, $newsletterField)
    {
        $this->em = $em;
        $this->userClass = $userClass;
        $this->newsletterField = $newsletterField;
    }

    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.RecipientProviderInterface::getRecipient()
     */
    public function getRecipient($id)
    {
        return $this->em->getRepository($this->userClass)->find($id);
    }

    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.RecipientProviderInterface::getNewsletterRecipientIDs()
     */
    public function getNewsletterRecipientIDs()
    {
        $qb = $this->em->createQueryBuilder()
            ->select("n.id")
            ->from($this->userClass, "n")
            ->where('n.'.$this->newsletterField.' = true')
            ->andWhere("n.locked = 0") // exclude locked users
            ->andWhere("n.enabled = 1") // exclude inactive users
            ;
        $results = $qb->getQuery()->execute();
        $ids = array();
        foreach ($results as $next) {
            $ids[] = $next['id'];
        }

        return $ids;
    }

}
