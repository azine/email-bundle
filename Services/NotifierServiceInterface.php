<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

interface NotifierServiceInterface
{
    public function sendNotifications(array &$failedAddresses): int;

    public function sendNewsletter(array &$failedAddresses): int;
}
