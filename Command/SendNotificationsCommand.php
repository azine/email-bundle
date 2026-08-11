<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Command;

use Azine\EmailBundle\Services\NotifierServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'emails:sendNotifications',
    description: 'Aggregate and send pending notification emails.',
)]
class SendNotificationsCommand extends Command
{
    public function __construct(
        private readonly NotifierServiceInterface $notifierService,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
The <info>emails:sendNotifications</info> command aggregates and sends pending notification emails.
Delivery uses the configured Symfony Mailer transport. For asynchronous delivery, route
Symfony Mailer messages through Messenger and operate the Messenger worker separately.
HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->lockFactory->createLock((string) $this->getName());
        if (!$lock->acquire()) {
            $output->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        try {
            $failedAddresses = [];
            $sentMails = $this->notifierService->sendNotifications($failedAddresses);

            $output->writeln(sprintf(
                '%s : %4d emails have been processed.',
                (new \DateTimeImmutable())->format(\DateTimeInterface::RFC2822),
                $sentMails,
            ));

            if ([] !== $failedAddresses) {
                $output->writeln((new \DateTimeImmutable())->format(\DateTimeInterface::RFC2822).' : The following email addresses failed:');
                foreach ($failedAddresses as $address) {
                    $output->writeln('    '.$address);
                }
            }

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
