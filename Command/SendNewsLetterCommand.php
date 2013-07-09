<?php
namespace Azine\EmailBundle\Command;


use Azine\EmailBundle\Services\AzineNotifierService;

use Symfony\Component\Console\Input\InputArgument;

use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Input\InputInterface;

use Azine\PlatformBundle\Services\SearcherService;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Send Newsletter via email
 * @author dominik
 */
class SendNewsletterCommand extends ContainerAwareCommand{


	/**
	 * (non-PHPdoc)
	 * @see Symfony\Component\Console\Command.Command::configure()
	 */
	protected function configure()
	{
		$this	->setName('notifications:sendNewsletter')
				->setDescription('Send Newsletter via email.')
				->setHelp(<<<EOF
The <info>notifications:sendNewsletter</info> command sends the newsletter email to all recipients who
indicate that they would like to recieve the newsletter (see .

Depending on you Swiftmailer-Configuration the email will be send directly or will be written to the spool.

If you configured Swiftmailer to spool email, then you need to run the <info>swiftmailer:spool:send</info>
command to actually send the emails from the spool.

EOF
				)
		;
	}

	/**
	 * (non-PHPdoc)
	 * @see Symfony\Component\Console\Command.Command::execute()
	 */
	protected function execute(InputInterface $input, OutputInterface $output){
		$failedAddresses = array();
		$sentMails = $this->getContainer()->get('azine_email_notifier_service')->sendNewsletter($failedAddresses);

		$output->writeln(str_pad($sentMails, 4, " ", STR_PAD_LEFT)." emails have been sent.");
		if(sizeof($failedAddresses) > 0){
			$output->writeln("The following email-addresses failed:");
			foreach ($failedAddresses as $address) {
				$output->writeln($address);
			}
		}
	}
}