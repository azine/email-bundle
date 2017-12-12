<?php
namespace Azine\EmailBundle\Tests;

use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Services\AzineTemplateProvider;
use Doctrine\ORM\EntityManager;

class TestHelper
{
    const TEST_EMAIL = 'test@example.com';
    const TEST_TOKEN = 'test_token';

    /**
     * @param EntityManager $manager
     * @param int $count
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public static function addSentEmails(EntityManager $manager, $count = 1)
    {
        while ($count > 0) {
            $sentEmail = new SentEmail();
            $sentEmail->setRecipients(array(self::TEST_EMAIL));
            $sentEmail->setSent(new \DateTime("2 weeks ago"));
            $sentEmail->setTemplate(AzineTemplateProvider::NEWSLETTER_TEMPLATE);
            $sentEmail->setVariables(array());
            $sentEmail->setToken($count == 1 ? self::TEST_TOKEN : self::TEST_TOKEN . $count);
            $manager->persist($sentEmail);

            $count--;
        }
        $manager->flush();
    }
}
