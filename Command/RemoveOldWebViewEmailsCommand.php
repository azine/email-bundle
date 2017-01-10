<?php
namespace Azine\EmailBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Remove emails stored for web-view that are older than the configured time.
 * @author dominik
 */
class RemoveOldWebViewEmailsCommand extends ContainerAwareCommand
{
    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command.Command::configure()
     */
    protected function configure()
    {
        $this	->setName('emails:remove-old-web-view-emails')
                ->setDescription('Remove all "SentEmail" from the database that are older than the configured time.')
                ->setDefinition(array(new InputArgument('keep', InputArgument::OPTIONAL, 'Remove all SentEmails older than "keep" days => also see azine_email_web_view_retention_time '),))
                ->setHelp(<<<EOF
The <info>emails:remove-old-web-view-emails</info> command deletes all SentEmail entities from the database
that are older than the number of days specified in the command-line parameter "keep" or configured in the
parameter "azine_email_web_view_retention" in your config.yml.
EOF
                )
        ;
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command.Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // get the number of days from the command-line-input
        $days = $input->getArgument('keep');

        // or if it is not given, from the config.yml
        if (!is_numeric($days)) {
            $days = $this->getContainer()->getParameter("azine_email_web_view_retention");
            $output->writeln("using the parameter from the configuration => '$days' days.");
        }

        if ($days === null) {
            throw new \Exception('either the commandline parameter "keep" or the "azine_email_web_view_retention" in your config.yml or the default-config has to be defined.');
        }

        // delete all SentEmails older than $date from the database
        $date = new \DateTime("$days days ago");
        $sentEmails = $this->getContainer()->get('doctrine')->getManager()->createQueryBuilder()
            ->delete("AzineEmailBundle:SentEmail","s")
            ->where("s.sent < :sent")
            ->setParameter("sent", $date);
        $q = $sentEmails->getQuery();
        $result = $q->execute();

        $output->writeln($result." SentEmails have been deleted that were older than ".$date->format("Y-m-d H:i:s"));
    }

}
