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
     * @param int $count
     */
    public static function addSentEmails(EntityManager $manager, $count = 1)
    {
        while ($count > 0) {
            $sentEmail = new SentEmail();
            $sentEmail->setRecipients(array(self::TEST_EMAIL));
            $sentEmail->setSent(new \DateTime('2 weeks ago'));
            $sentEmail->setTemplate(AzineTemplateProvider::NEWSLETTER_TEMPLATE);
            $sentEmail->setVariables(array());
            $sentEmail->setToken(1 == $count ? self::TEST_TOKEN : self::TEST_TOKEN.$count);
            $manager->persist($sentEmail);

            --$count;
        }
        $manager->flush();
    }


    /**
     * @param $url
     * @param $locale
     * @return false|string
     */
    public static function makeAbsolutPath($url, $locale){
        $indexOfLocale = strpos($url, "/$locale/");
        if($indexOfLocale!==false){
            return substr($url, $indexOfLocale);
        }
        $hostStartIndex = strpos($url, "http") !== false ? strpos($url, "//") + 2 : 0;
        $appEndIndex = strpos($url, ".php/", $hostStartIndex );
        if($appEndIndex !== false){
            return substr($url, $appEndIndex + 4 );
        }

        $hostEndIndex = strpos($url, "/", $hostStartIndex);
        return substr($url, $hostEndIndex);
    }

}
