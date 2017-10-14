<?php
namespace Azine\EmailBundle\Tests;

use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Services\AzineTemplateProvider;
use Doctrine\ORM\EntityManager;

class TestHelper
{
    /**
     * @param integer $count
     */
    public static function addSentEmails(EntityManager $manager, $count)
    {
        while ($count > 0) {
            $sentEmail = new SentEmail();
            $sentEmail->setRecipients(array('dominik@businger.ch'));
            $sentEmail->setSent(new \DateTime("2 weeks ago"));
            $sentEmail->setTemplate(AzineTemplateProvider::NEWSLETTER_TEMPLATE);
            $sentEmail->setVariables(array());
            $sentEmail->setToken('fdasdfasfafsadf');
            $manager->persist($sentEmail);

            $count--;
        }
        $manager->flush();
    }
 }
