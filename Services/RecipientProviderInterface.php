<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

use Azine\EmailBundle\Entity\RecipientInterface;

interface RecipientProviderInterface
{
    public function getRecipient(int|string $id): RecipientInterface;

    /** @return list<int|string> */
    public function getNewsletterRecipientIDs(): array;
}
