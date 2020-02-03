<?php

namespace Azine\EmailBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Send Newsletter via email.
 *
 * @author dominik
 */
class SendNewsLetterCommand extends ContainerAwareCommand
{
    /**
     * (non-PHPdoc).
     *
     * @see Symfony\Component\Console\Command.Command::configure()
     */
    protected function configure()
    {
        $this->setName('emails:sendNewsletter')
                ->setDescription('Send Newsletter via email to all subscribers.')
                ->setHelp(<<<EOF
The <info>emails:sendNewsletter</info> command sends the newsletter email to all recipients who
indicate that they would like to recieve the newsletter (see Azine\EmailBundle\Entity\RecipientInterface.getNewsletter).

Depending on you Swiftmailer-Configuration the email will be send directly or will be written to the spool.

If you configured Swiftmailer to spool email, then you need to run the <info>swiftmailer:spool:send</info>
command to actually send the emails from the spool.

EOF
                )
        ;
    }

    /**
     * (non-PHPdoc).
     *
     * @see Symfony\Component\Console\Command.Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (class_exists('Symfony\Component\Filesystem\LockHandler')) {
            $lock = new \Symfony\Component\Filesystem\LockHandler($this->getName());
            $unlockedCommand = $lock->lock();
        } elseif (class_exists('\Symfony\Component\Lock\Store\SemaphoreStore') && class_exists('\Symfony\Component\Lock\Factory')) {
            $store = new \Symfony\Component\Lock\Store\SemaphoreStore();
            $factory = new \Symfony\Component\Lock\Factory($store);

            $lock = $factory->createLock($this->getName());
            $unlockedCommand = $lock->acquire();
        } else {
            $output->writeln('Unable to identify available Locking classes. Running without locks');
            $unlockedCommand = true;
        }

        if (!$unlockedCommand) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        $failedAddresses = array();
        $output->writeln(date(\DateTime::RFC2822).' : starting to send newsletter emails.');

        $sentMails = $this->getContainer()->get('azine_email_notifier_service')->sendNewsletter($failedAddresses);

        $output->writeln(date(\DateTime::RFC2822).' : '.str_pad($sentMails, 4, ' ', STR_PAD_LEFT).' newsletter emails have been sent.');
        if (sizeof($failedAddresses) > 0) {
            $output->writeln(date(\DateTime::RFC2822).' : '.'The following email-addresses failed:');
            foreach ($failedAddresses as $address) {
                $output->writeln('       '.$address);
            }
        }
    }
}
