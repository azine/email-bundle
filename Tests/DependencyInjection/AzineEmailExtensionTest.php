<?php
namespace Azine\EmailBundle\Tests\DependencyInjection;

use Azine\EmailBundle\DependencyInjection\AzineEmailExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

class AzineEmailExtensionTest extends \PHPUnit_Framework_TestCase
{
    /** @var ContainerBuilder */
    protected $configuration;

    /**
     * This should not throw an exception
     */
    public function testMinimalConfig()
    {
        $loader = new AzineEmailExtension();
        $config = $this->getMinimalConfig();
        $loader->load(array($config), new ContainerBuilder());
    }

    /**
     * This should not throw an exception
     */
    public function testFullConfig()
    {
        $loader = new AzineEmailExtension();
        $config = $this->getFullConfig();
        $loader->load(array($config), new ContainerBuilder());
    }

    /**
     * This should throw an exception
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testConfigWithMissingRecipientClass()
    {
        $loader = new AzineEmailExtension();
        $config = $this->getFullConfig();
        unset($config['recipient_class']);
        $loader->load(array($config), new ContainerBuilder());
    }

    /**
     * This should throw an exception
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testConfigWithMissingTemplateProvider()
    {
        $loader = new AzineEmailExtension();
        $config = $this->getFullConfig();
        unset($config['template_provider']);
        $loader->load(array($config), new ContainerBuilder());
    }

    /**
     * This should throw an exception
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testConfigWithMissingEmailAddress()
    {
        $loader = new AzineEmailExtension();
        $config = $this->getFullConfig();
        unset($config['no_reply']['email']);
        $loader->load(array($config), new ContainerBuilder());
    }

    /**
     * This should throw an exception
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testConfigWithMissingEmailName()
    {
        $loader = new AzineEmailExtension();
        $config = $this->getFullConfig();
        unset($config['no_reply']['name']);
        $loader->load(array($config), new ContainerBuilder());
    }

    /**
     * This should throw an exception
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testConfigWithMissingEmail()
    {
        $loader = new AzineEmailExtension();
        $config = $this->getFullConfig();
        unset($config['no_reply']);
        $loader->load(array($config), new ContainerBuilder());
    }

    public function testCustomConfiguration()
    {
        $this->configuration = new ContainerBuilder();
        $loader = new AzineEmailExtension();
        $config = $this->getFullConfig();
        $config['recipient_class'] 				= 'TestRecipientClass';
        $config['recipient_newsletter_field'] 	= 'some_field';
        $config['template_provider'] 			= 'TestTemplateProvider';
        $config['notifier_service'] 			= 'TestNotifierService';
        $config['recipient_provider']			= 'TestRecipientService';
        $config['template_twig_swift_mailer'] 	= 'TestTwigSwiftMailer';
        $config['no_reply']['email'] 			= 'test@email.com';
        $config['no_reply']['name'] 			= 'test name';
        $config['image_dir'] 					= '/tmp';

        $loader->load(array($config), $this->configuration);

        $this->assertParameter('TestRecipientClass',	'azine_email_recipient_class');
        $this->assertParameter('some_field',			'azine_email_recipient_newsletter_field');

        $mailArray = $this->configuration->getParameter('azine_email_no_reply');
        $this->assertEquals('test name',		$mailArray['name'], "The no-reply-name is not correct.");
        $this->assertEquals('test@email.com',	$mailArray['email'], "The no-reply-email is not correct.");

        $this->assertParameter('/tmp',					'azine_email_image_dir');

        $this->assertAlias('testtemplateprovider',		'azine_email_template_provider');
        $this->assertAlias('testnotifierservice',		'azine_email_notifier_service');
        $this->assertAlias('testrecipientservice',		'azine_email_recipient_provider');
        $this->assertAlias('testtwigswiftmailer',		'azine_email_template_twig_swift_mailer');
    }

    protected function createEmptyConfiguration()
    {
        $this->configuration = new ContainerBuilder();
        $loader = new AzineEmailExtension();
        $config = $this->getEmptyConfig();
        $loader->load(array($config), $this->configuration);
        $this->assertTrue($this->configuration instanceof ContainerBuilder);
    }

    protected function createFullConfiguration()
    {
        $this->configuration = new ContainerBuilder();
        $loader = new AzineEmailExtension();
        $config = $this->getFullConfig();
        $loader->load(array($config), $this->configuration);
        $this->assertTrue($this->configuration instanceof ContainerBuilder);
    }

    /**
     * Get the minimal config
     * @return array
     */
    protected function getMinimalConfig()
    {
        $yaml = <<<YAML
recipient_class: 'Azine\\PlatformBundle\\Entity\\User'
template_provider: 'azine_platform.emailtemplateprovider'
no_reply:
  email: 'no-reply@azine.me'
  name: 'azine.me notification daemon'
YAML;

        return Yaml::parse($yaml);
    }

    /**
     * Get a full config for this bundle
     */
    protected function getFullConfig()
    {
        $yaml = <<<YAML
recipient_class: 'Acme\\SomeBundle\\Entity\\User'
recipient_newsletter_field: 'newsletter'
notifier_service: 'azine_email.example.notifier_service'
template_provider: 'azine_email.example.template_provider'
recipient_provider: 'azine_email.default.recipient_provider'
template_twig_swift_mailer: 'azine_email.default.template_twig_swift_mailer'
no_reply:
  email: 'no-reply@example.com'
  name: 'notification daemon'
image_dir: '%kernel.root_dir%/../vendor/azine/email-bundle/Azine/EmailBundle/Resources/htmlTemplateImages/'
allowed_images_folders:
  - '%kernel.root_dir%/../vendor/azine/email-bundle/Azine/EmailBundle/Resources/htmlTemplateImages/'
  - '%kernel.root_dir%/../vendor/azine/email-bundle/Azine/EmailBundle/Resources/'
YAML;

        return Yaml::parse($yaml);
    }

    /**
     * @param string $value
     * @param string $key
     */
    private function assertAlias($value, $key)
    {
        $this->assertEquals($value, (string) $this->configuration->getAlias($key), sprintf('%s alias is correct', $key));
    }

    /**
     * @param string $value
     * @param string $key
     */
    private function assertParameter($value, $key)
    {
        $this->assertEquals($value, $this->configuration->getParameter($key), sprintf('%s parameter is correct', $key));
    }

    /**
     * @param string $id
     */
    private function assertHasDefinition($id)
    {
        $this->assertTrue(($this->configuration->hasDefinition($id) ?: $this->configuration->hasAlias($id)));
    }

    /**
     * @param string $id
     */
    private function assertNotHasDefinition($id)
    {
        $this->assertFalse(($this->configuration->hasDefinition($id) ?: $this->configuration->hasAlias($id)));
    }

    protected function tearDown()
    {
        unset($this->configuration);
    }
}
