<?php
namespace Azine\EmailBundle\Services;

use Doctrine\ORM\EntityManager;

/**
 * Default implementation of the RecipientProviderInterface
 */
 class AzineRecipientProvider implements RecipientProviderInterface {

	/** @var EntityManager */
	private $em;

	/** @var string your recipient class */
	private $userClass;

	/** @var string the field name of the boolean field that indicate wether or not a newsletter should be sent to the recipient entity. */
	private $newsLetterField;

	/**
	 *
	 * @param EntityManager $em
	 * @param unknown_type $userClass
	 * @param unknown_type $newsLetterField
	 */
	public function __construct(EntityManager $em, $userClass, $newsLetterField) {
		$this->em = $em;
		$this->userClass = $userClass;
		$this->newsLetterField = $newsLetterField;
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.RecipientProviderInterface::getRecipient()
	 */
	public function getRecipient($id){
		return $this->em->getRepository($this->userClass)->find($id);
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.RecipientProviderInterface::getNewsLetterRecipientIDs()
	 */
	public function getNewsLetterRecipientIDs(){
		$qb = $this->em->createQueryBuilder()
			->select("n.id")
			->from($this->userClass, "n")
			->where('n.'.$this->newsLetterField.' = true');
		$results = $qb->getQuery()->execute();
		$ids = array();
		foreach ($results as $next){
			$ids[] = $next['id'];
		}
		return $ids;
	}

}