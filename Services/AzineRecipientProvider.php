<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

use Azine\EmailBundle\Entity\RecipientInterface;
use Doctrine\Persistence\ManagerRegistry;

class AzineRecipientProvider implements RecipientProviderInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly string $userClass,
        private readonly string $newsletterField,
    ) {
    }

    public function getRecipient(int|string $id): RecipientInterface
    {
        $recipient = $this->managerRegistry->getManager()->getRepository($this->userClass)->find($id);
        if (!$recipient instanceof RecipientInterface) {
            throw new \RuntimeException(sprintf(
                'No recipient of class "%s" was found for id "%s".',
                $this->userClass,
                $id,
            ));
        }

        return $recipient;
    }

    public function getNewsletterRecipientIDs(): array
    {
        $rows = $this->managerRegistry
            ->getManager()
            ->createQueryBuilder()
            ->select('recipient.id')
            ->from($this->userClass, 'recipient')
            ->where(sprintf('recipient.%s = true', $this->newsletterField))
            ->andWhere('recipient.enabled = true')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(
            static fn (array $row): int|string => $row['id'],
            $rows,
        ));
    }
}
