<?php
namespace Azine\EmailBundle\Tests\Command;

use Azine\EmailBundle\Services\AzineNotifierService;

/**
 * This Service compiles and renders the emails to be sent.
 * @author Dominik Businger
 */
class AzineNotifierServiceMock extends AzineNotifierService
{
    private $fail = false;

    const FAILED_ADDRESS = "a.failed@address.com";
    const EMAIL_COUNT = 10;

    public function __construct($fail = false)
    {
        $this->fail = $fail;
    }

    public function sendNotifications(array &$failedAddresses)
    {
        if ($this->fail) {
            $failedAddresses[] = self::FAILED_ADDRESS;

            return self::EMAIL_COUNT - 1;
        }

        return self::EMAIL_COUNT;
    }

    public function sendNewsletter(array &$failedAddresses)
    {
        if ($this->fail) {
            $failedAddresses[] = self::FAILED_ADDRESS;

            return self::EMAIL_COUNT - 1;
        }

        return self::EMAIL_COUNT;
    }
}
