<?php

namespace Azine\EmailBundle\Tests\Functional;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Azine\EmailBundle\Entity\Notification;
use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Services\AzineNotifierService;
use Azine\EmailBundle\Services\AzineRecipientProvider;
use Azine\EmailBundle\Services\AzineTemplateProvider;
use Azine\PlatformBundle\DataFixtures\ORM\LoadBaseData;
use Azine\PlatformBundle\Services\NotifierService;
use Azine\PlatformBundle\Tests\AzineBaseTestWithServices;
use Azine\PlatformBundle\Tests\FindInFile;
use Doctrine\ORM\EntityManager;
use FOS\UserBundle\Model\User;
use FOS\UserBundle\Model\UserManager;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

class EmailImagesInEmailAndWebViewTest extends WebTestCase
{
    /** @var User */
    private $testRecipient;

    /** @var string */
    private $originalUserPassword;

    /** @var string */
    private $originalUserSalt;

    /** @var ContainerInterface */
    private $appContainer;

    /** @var string */
    private $uniqueId;

    /** @var array */
    private $testImages = array();

    public function setUp()
    {
        $this->uniqueId = md5(microtime() . "_" . random_int(0,1000));

        // make sure there is an application
        $this->checkApplication();

        $this->appContainer = $this->getKernel()->getContainer();

        // empty the spool directory
        $this->cleanMailSpoolDirectory();

        // create the test-recipient/user for this test
        $this->testRecipient = $this->getTestRecipient();

        // copy sample-image to all allowed image folders
        $allowedImageFolders = $this->appContainer->getParameter("azine_email_allowed_images_folders");
        $allowedImageFolders[] = $this->appContainer->getParameter("azine_email_image_dir");
        $allowedImageFolders = array_unique($allowedImageFolders);
        $testImage = $this->uniqueId."-test_image.jpg";
        foreach ($allowedImageFolders as $nextAllowedImageFolder){
            $targetFile = realpath($nextAllowedImageFolder)."/".$testImage;
            copy(__DIR__."/../../Resources/htmlTemplateImages/logo.png", $targetFile);
            $this->testImages[] = $targetFile;
        }
    }

    public function testImagesEmbededAndReferencedInEmailAndImagesReferencedInWebView()
    {

        $uniqueSubject = 'email-subject-for-test-case-' . $this->uniqueId;
        $notification = new Notification();
        $notification->setCreatedValue();
        $notification->setContent('content for' . $uniqueSubject);
        $notification->setTitle('title for ' . $uniqueSubject);
        $notification->setTemplate(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE);
        $notification->setRecipientId($this->testRecipient->getId());
        $notification->setSendImmediately(false);
        $notification->setImportance(Notification::IMPORTANCE_NORMAL);
        $em = $this->getEntityManager();
        $em->persist($notification);
        $em->flush($notification);
        $em->refresh($notification);

        $contentItems = array(array(AzineTemplateProvider::CONTENT_ITEM_MESSAGE_TEMPLATE => array('notification' => $notification)));

        $this->sendEmail($contentItems, $uniqueSubject);
        $this->verifyEmbedding();
        $this->verifyWebView();
    }


    public function testImagesFromAllConfiguredAllowedFolders()
    {
        // create template/content-item to show all images
        $contentItems = array();
        foreach ($this->testImages as $testImage) {
            $contentItems[] = array('AzineEmailBundle:contentItem:image-test-message' => array('title' => "message for $testImage", 'test_image' => $testImage, 'original_location' => "file: $testImage"));
        }

        $this->sendEmail($contentItems, 'email-subject-for-test-case-' . $this->uniqueId);
        $this->verifyEmbedding();
        $this->verifyWebView();
    }

    public function tearDown()
    {
        // revert the test-User password & salt
        $this->testRecipient->setPassword($this->originalUserPassword);
        $this->testRecipient->setSalt($this->originalUserSalt);
        $this->getEntityManager()->flush();

        // remove sample-image from all folders
        foreach ($this->testImages as $testImage) {
            unlink($testImage);
        }
    }

    private function sendEmail(array $contentItems, string $subjectLine)
    {
        /** @var AzineNotifierService $notifierService */
        $notifierService = $this->appContainer->get('azine_email.example.notifier_service');

        $params = array('subject' => $subjectLine);
        $params = array_merge($params, $notifierService->getRecipientSpecificNewsletterParams($this->testRecipient));
        $params[NotifierService::CONTENT_ITEMS] = $contentItems;

        $newsletterTemplate = "AzineEmailBundle::newsletterEmailLayout";
        $notifierService->sendNewsletterFor($this->testRecipient, $params, $newsletterTemplate);
    }

    private function verifyWebView()
    {
        // find the webViewToken for the sent email & check the images in the webView
        /** @var SentEmail $sentEmail */
        $sentEmails = $this->getEntityManager()->getRepository(SentEmail::class)->createQueryBuilder("e")
            ->where("e.recipients like :recipientEmail and e.variables like :uniqueId")
            ->setParameter('recipientEmail', "%" . $this->testRecipient->getEmail() . "%")
            ->setParameter('uniqueId', "%" . $this->uniqueId . "%")
            ->getQuery()
            ->execute();
        $this->assertEquals(1, sizeof($sentEmails));
        $sentEmail = $sentEmails[0];

        /** @var RouterInterface $router */
        $router = $this->appContainer->get("router");
        $router->getContext()->setParameter('_locale', $this->testRecipient->getPreferredLocale());
        $router->getContext()->setPathInfo("/");
        $webViewUrl = "/".$router->generate('azine_email_webview', array('token' => $sentEmail->getToken()), RouterInterface::RELATIVE_PATH);

        $client = static::createClient();
        $client->followRedirects();

        // browse to webView
        $this->loginTestUserIfRequired($client, $webViewUrl);
        $crawler = $client->getCrawler();
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // check that the page loaded correctly and the referenced image urls load as well
        $urls = array();
        $crawler->filter("img")->each(function (Crawler $nextImage) use (&$urls) {
            $urls[] = substr($nextImage->image()->getUri(), strpos( $nextImage->image()->getUri(), "/en/"));
        });

        foreach ($urls as $nextUrl) {
            $imageCrawler = $client->request("GET", $nextUrl);
            $statusCode = $client->getResponse()->getStatusCode();
            ($client->getRequest()->getUri());
            $this->assertEquals(200, $statusCode, "Image failed to load correctly.");
        }

    }

    private function verifyEmbedding()
    {
        // find the source of the sent email & check images
        $messageFiles = $this->findTextInSpooledTestEmail($this->uniqueId);
        $this->assertEquals(1, sizeof($messageFiles));
        $messageContent = file_get_contents($messageFiles[0]);

        /** @var \Swift_Message $sentSwiftMessage */
        $sentSwiftMessage = unserialize($messageContent);
        $matches = array();
        preg_match_all('/cid:(.*?generated)/', $sentSwiftMessage->getBody(), $matches);
        $children = $sentSwiftMessage->getChildren();
        foreach ($matches[1] as $match) {
            $found = false;
            foreach ($children as $child) {
                if ($child instanceof \Swift_Image && $child->getId() == $match) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'image not found as embedded.');
        }
    }

    private function loginTestUserIfRequired(Client $client, $url)
    {
        $crawler = $client->request("GET", $url);
        // login if required
        if (stripos($crawler->filter("title")->text(), 'login') !== false) {
            $form = $crawler->filter("form")->form(array('_username' => $this->testRecipient->getUsername(), '_password' => $this->uniqueId));
            $crawler = $client->submit($form);
            $crawler = $client->request("GET", $url);
        }
    }

    /**
     * @return EntityManager
     */
    private function getEntityManager()
    {
        return $this->getKernel()->getContainer()->get('doctrine')->getManager();
    }

    /**
     * Check if the current setup is a full application.
     * If not, mark the test as skipped else continue.
     */
    private function checkApplication()
    {
        try {
            $this->getKernel();
        } catch (\RuntimeException $ex) {
            $this->markTestSkipped('There does not seem to be a full application available (e.g. running tests on travis.org). So this test is skipped.');

            return;
        }
    }

    /**
     * Delete all files in the spool directory
     */
    private function cleanMailSpoolDirectory()
    {
        $files = glob($this->getSpoolDirectory() . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @return \AppKernel
     */
    private function getKernel()
    {
        if (null == static::$kernel) {
            static::$kernel = static::createKernel();
            static::$kernel->boot();
        }

        return static::$kernel;
    }

    /**
     * Search for a spooled email with the given string in its content.
     *
     * @param string $searchString
     *
     * @return array of files with the searchString
     */
    private function findTextInSpooledTestEmail($searchString)
    {
        $findInFile = new FindInFile();
        $findInFile->excludeMode = false;
        $findInFile->formats = array('.message');
        $result = $findInFile->find($this->getSpoolDirectory(), $searchString);
        return $result;
    }

    /**
     * Get the configured spool directory
     * @return string
     */
    private function getSpoolDirectory()
    {
        return $this->getKernel()->getContainer()->getParameter('swiftmailer.spool.defaultMailer.file.path');
    }

    /**
     * @return User
     * @throws \Exception
     */
    private function getTestRecipient()
    {
        /** @var UserManager $userManager */
        $userManager = $this->appContainer->get('fos_user.user_manager');
        /** @var User $testUser */
        $testUser = $userManager->findUsers()[0];
        $this->originalUserPassword = $testUser->getPassword();
        $this->originalUserSalt = $testUser->getSalt();
        $testUser->setPlainPassword($this->uniqueId);
        $userManager->updateUser($testUser, true);
        return $testUser;
    }
}
