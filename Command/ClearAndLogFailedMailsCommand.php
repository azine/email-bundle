<?php
namespace Azine\EmailBundle\Command;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

use \Swift_Transport;

/**
 * Try to send emails that failed and are still pending in the spool-folder.
 *
 * After trying to send them one more time, delete the files and log any email-address that still failed.
 *
 * @author dominik
 *
 */
class ClearAndLogFailedMailsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('emails:clear-and-log-failures')
            ->setDescription('Clears and logs failed emails from the spool')
            ->setDefinition(array(new InputArgument(	'date',
                                                        InputArgument::OPTIONAL,
                                                        'Try to send and then delete all failed emails that are older than "date". The date must be something that strtotime() is able to parse:  => e.g. "since yesterday", "until 2 days ago", "> now - 2 hours", ">= 2005-10-15" '
                                                    ),
                            ))
            ->setHelp(<<<EOF
The <info>emails:clear-and-log-failures</info> command tries to send failed emails and deletes them
from the spool directory after this last try. Any email-address that still failed, is logged.
EOF
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $failedRecipients = array();

        // check if the current environment is configured to spool emails
        try {
            /** @var $transport \Swift_Transport */
            $transport = $this->getContainer()->get('swiftmailer.transport.real');

        } catch (ServiceNotFoundException $ex) {
            $output->writeln("\n\n\nCould not load transport. Is file-spooling configured in your config.yml for this environment?\n\n\n");

            return;
        }

        try {
            $mailers = $this->getContainer()->getParameter("swiftmailer.mailers");
            $mailerName = key($mailers);
            $spoolPath = $this->getContainer()->getParameter("swiftmailer.spool.$mailerName.file.path");
        } catch (InvalidArgumentException $ex) {
            $output->writeln("\n\n\nCould not find file spool path. Is file-spooling configured in your config.yml for this environment?\n\n\n");

            return;
        }

        // start the mail transport
        if (!$transport->isStarted()) {
            $transport->start();
        }

        // find pending mails and try to send them again now
        $finder = Finder::create()->in($spoolPath)->name('*.sending');

        $date = $input->getArgument('date');

        if ($date) {
            $finder->date($date);
        }

        if ($finder->count() == 0) {
            $output->writeln("No failed-message-files found in '$spoolPath' for retry.");

            return;
        }

        foreach ($finder as $failedFile) {
            // rename the file, so no other process tries to find it
            $tmpFilename = $failedFile.'.finalretry';
            rename($failedFile, $tmpFilename);

            /** @var $message \Swift_Message */
            $message = unserialize(file_get_contents($tmpFilename));
            $output->writeln(sprintf(
                    "Retrying to send '<info>%s</info>' to '<info>%s</info>'",
                    $message->getSubject(),
                    implode(', ', array_keys($message->getTo()))
            ));

            try {
                $transport->send($message, $failedRecipients);
                $output->writeln('Sent!');
            } catch (\Swift_TransportException $e) {
                $output->writeln('<error>Send failed - deleting spooled message</error>');
            }

            // delete the file, either because it sent, or because it failed
            unlink($tmpFilename);
        }

        // write the failure to the log
        if (sizeof($failedRecipients) > 0) {
            /** @var $logger LoggerInterface */
            $logger = $this->getContainer()->get("logger");
            $logger->warning("<error>Failed to send an email to : ".implode(", ", $failedRecipients)."</error>");
        }
    }
}
