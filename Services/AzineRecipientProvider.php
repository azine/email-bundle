<?php
namespace Azine\EmailBundle\Services;

use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * Default implementation of the RecipientProviderInterface
 */
class AzineRecipientProvider implements RecipientProviderInterface
{
    /** @var ManagerRegistry */
    private $managerRegistry;

    /** @var string your recipient class */
    private $userClass;

    /** @var string the field name of the boolean field that indicate wether or not a newsletter should be sent to the recipient entity. */
    private $newsletterField;

    /**
     *
     * @param ManagerRegistry $managerRegistry
     * @param string          $userClass
     * @param string          $newsletterField
     */
    public function __construct(ManagerRegistry $managerRegistry, $userClass, $newsletterField)
    {
        $this->managerRegistry = $managerRegistry;
        $this->userClass = $userClass;
        $this->newsletterField = $newsletterField;
    }

    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.RecipientProviderInterface::getRecipient()
     */
    public function getRecipient($id)
    {
        return $this->managerRegistry->getManager()->getRepository($this->userClass)->find($id);
    }

    /**
     * (non-PHPdoc)
     * @see Azine\EmailBundle\Services.RecipientProviderInterface::getNewsletterRecipientIDs()
     */
    public function getNewsletterRecipientIDs()
    {
        $qb = $this->managerRegistry->getManager()->createQueryBuilder()
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
