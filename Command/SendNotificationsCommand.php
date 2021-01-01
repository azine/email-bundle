<?php

namespace Azine\EmailBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Aggregate and send pending notifications or newsletters via email.
 *
 * @author dominik
 */
class SendNotificationsCommand extends ContainerAwareCommand
{
    /**
     * (non-PHPdoc).
     *
     * @see Symfony\Component\Console\Command.Command::configure()
     */
    protected function configure()
    {
        $this->setName('emails:sendNotifications')
                ->setDescription('Aggregate and send pending notifications via email.')
                ->setHelp(<<<EOF
The <info>emails:sendNotifications</info> command sends emails for all pending notifications.

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
        $sentMails = $this->getContainer()->get('azine_email_notifier_service')->sendNotifications($failedAddresses);

        $output->writeln(date(\DateTime::RFC2822).' : '.str_pad($sentMails, 4, ' ', STR_PAD_LEFT).' emails have been processed.');
        if (sizeof($failedAddresses) > 0) {
            $output->writeln(date(\DateTime::RFC2822).' : '.'The following email-addresses failed:');
            foreach ($failedAddresses as $address) {
                $output->writeln('    '.$address);
            }
        }

        // (optional) release the lock (otherwise, PHP will do it for you automatically)
        if (isset($lock) && method_exists($lock, '$release')) {
            $lock->release();
        }
    }
}
