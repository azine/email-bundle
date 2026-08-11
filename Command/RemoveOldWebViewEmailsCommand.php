<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Command;

use Azine\EmailBundle\Entity\SentEmail;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'emails:remove-old-web-view-emails',
    description: 'Remove stored email web views older than the configured retention period.',
)]
class RemoveOldWebViewEmailsCommand extends Command
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'keep',
                InputArgument::OPTIONAL,
                'Remove stored emails older than this number of days.',
            )
            ->setHelp(<<<'HELP'
The <info>emails:remove-old-web-view-emails</info> command deletes SentEmail entities older than
"keep" days. When the argument is omitted, azine_email.web_view_retention is used.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argument = $input->getArgument('keep');
        $days = is_numeric($argument) ? (int) $argument : $this->retentionDays;

        if ($days < 1) {
            $output->writeln('<error>The web-view retention period must be at least one day.</error>');

            return Command::INVALID;
        }

        if (!is_numeric($argument)) {
            $output->writeln(sprintf('Using the configured retention period: %d days.', $days));
        }

        $cutoff = new \DateTimeImmutable(sprintf('-%d days', $days));
        $deleted = $this->managerRegistry
            ->getManager()
            ->createQueryBuilder()
            ->delete(SentEmail::class, 's')
            ->where('s.sent < :sent')
            ->setParameter('sent', $cutoff)
            ->getQuery()
            ->execute();

        $output->writeln(sprintf(
            '%d SentEmails older than %s were deleted.',
            (int) $deleted,
            $cutoff->format('Y-m-d H:i:s'),
        ));

        return Command::SUCCESS;
    }
}
