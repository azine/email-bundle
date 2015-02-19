Azine Email Bundle
==================

Welcome to Azine Email Bundle quick start :

- installing Azine Email Bundle.
- default config to work with.
- your user bundle implementation to work with Azine Email Bundle .
- default template layout.
- send newsletter.

You can easily use it with transactional email services like mailgun.com.


## Installation
To install AzineGeoBlockingBundle with Composer just add the following to your `composer.json` file:

```
// composer.json
{
    // ...
    require: {
        // ...
        "azine/email-bundle": "dev-master"
    }
}
```
Then, you can install the new dependencies by running Composer’s update command from 
the directory where your `composer.json` file is located:

```
php composer.phar update
```
Now, Composer will automatically download all required files, and install them for you. 
All that is left to do is to update your AppKernel.php file, and register the new bundle:

```
<?php

// in AppKernel::registerBundles()
$bundles = array(
    // ...
    new Azine\EmailBundle\AzineEmailBundle(),
    // ...
);
```

Register the routes of the AzineEmailBundle:

```
// in app/config/routing.yml

azine_email_bundle:
    resource: "@AzineEmailBundle/Resources/config/routing.yml"
    
```

## Configuration options
For the bundle to work with the default-settings, no config-options are 
required, but the swiftmailer must be configured.

This is the complete list of configuration options with their defaults.
```
// app/config/config.yml
azine_email:

    # the class of your implementation of the RecipientInterface
    recipient_class:      Acme\SomeBundle\Entity\User # Required

    # the fieldname of the boolean field on the recipient class indicating, that a newsletter should be sent or not
    recipient_newsletter_field:  newsletter

    # the service-id of your implementation of the nofitier service to be used
    notifier_service:     azine_email.example.notifier_service

    # the service-id of your implementation of the template provider service to be used
    template_provider:    azine_email.example.template_provider # Required

    # the service-id of the implementation of the RecipientProviderInterface to be used
    recipient_provider:   azine_email.default.recipient_provider

    # the service-id of the mailer service to be used
    template_twig_swift_mailer:  azine_email.default.template_twig_swift_mailer
    no_reply:             # Required

        # the no-reply email-address
        email:                no-reply@example.com # Required

        # the name to appear with the 'no-reply'-address.
        name:                 notification daemon # Required

    # absolute path to the image-folder containing the images used in your templates.
    image_dir:            %kernel.root_dir%/../vendor/azine/email-bundle/Azine/EmailBundle/Resources/htmlTemplateImages/

    # list of folders from which images are allowed to be embedded into emails
    allowed_images_folders:  []

    # newsletter configuration
    newsletter:

        # number of days between newsletters
        interval:             14

        # time of the day, when newsletters should be sent, 24h-format => e.g. 23:59
        send_time:            10:00

    # templates configuration
    templates:

        # wrapper template id (without ending) for the newsletter
        newsletter:           AzineEmailBundle::newsletterEmailLayout

        # wrapper template id (without ending) for notifications
        notifications:        AzineEmailBundle::notificationsEmailLayout

        # template id (without ending) for notification content items
        content_item:         AzineEmailBundle:contentItem:message

    # the parameter to be used do identify campaigns in urls
    campaign_param_name:  pk_campaign # defaults work with piwik, but you can change them to work with adWords

    # the parameter to be used do identify campaign-keywords in urls
    campaign_keyword_param_name:  pk_kwd # defaults work with piwik, but you can change them to work with adWords

    # number of days that emails should be available in web-view
    web_view_retention:   90

    # the service-id of your implementation of the web view service to be used
    web_view_service:     azine_email.example.web_view_service

```

### so far so good !!

we have just copy paste some codes , but we need explain on config section :
```
    recipient_class:      Acme\SomeBundle\Entity\User
``` 
this is user class that we will talk later on this document but you should register your own User Bundle here .
```
	notifier_service:     azine_email.example.notifier_service
```	
this means we will use this service to send our newsletter but this is example service and it's not what we expected to work or be at time but it's very good sample we need so follow this :
copy ExampleNotifierService.php from email-bundle/Services to your bundle like this :  namespace/yourbundle/Services/ExampleNotifierService.php

then edit it  : 

```
  namespace namespace\yourbundle\Services;
  .
  .
  .
  .
  
  $contentItems[] = array('yourbundle:foo:my_email_content_body_template' => $templateParams); // your own template name
  .
  .
  .
  .
  $contentItems[] = array('yourbundle:foo:my_email_content_body_template' => $recipientSpecificTemplateParams); // your own template name
```
ofcurse you need 2 more file in your template :
```
  yourbundle/Resource/views/foo/my_email_content_body_template.txt.twig
  yourbundle/Resource/views/foo/my_email_content_body_template.html.twig
```
still we need to tell config file to use this service .  
copy these lines to your config file :

```
services:

    azine_email.default.template_twig_swift_mailer:
        class: Azine\EmailBundle\Services\AzineTwigSwiftMailer
        arguments:
          mailer:             "@mailer"
          router:             "@router"
          twig:               "@twig"
          logger:             "@logger"
          translator:         "@translator.default"
          templateProvider:   "@azine_email_template_provider"
          entityManager:      "@doctrine.orm.entity_manager"
          parameters:
            template:
              confirmation :  "%fos_user.registration.confirmation.template%"
              resetting:      "%fos_user.resetting.email.template%"
            from_email:
              confirmation:   "%fos_user.registration.confirmation.from_email%"
              resetting:      "%fos_user.resetting.email.from_email%"
            no_reply:         "%azine_email_no_reply%"
          immediateMailer:    "@mailer" # we assume that we will use default swiftmailer config to send immediate emails  
          
    azine_email.yourbundle.notifier_service: # your bundle name that contain new ExampleNotifierService file
        class: namespace\yourbundle\Services\ExampleNotifierService # your new class
        arguments:
          mailer:             "@azine_email_template_twig_swift_mailer"
          twig:               "@twig"
          logger:             "@logger"
          router:             "@router"
          entityManager:      "@doctrine.orm.entity_manager"
          templateProvider:   "@azine_email_template_provider"
          recipientProvider:  "@azine_email_recipient_provider"
          translatorService:  "@translator.default"
          parameters:
            newsletter_interval : "%azine_email_newsletter_interval%"
            newsletter_send_time: "%azine_email_newsletter_send_time%"
            templates_newsletter:     "%azine_email_templates_newsletter%"
            templates_notifications:  "%azine_email_templates_notifications%"
            templates_content_item:   "%azine_email_templates_content_item%"
```			

on your config file change 
```
   notifier_service:     azine_email.example.notifier_service			
```			
to
```
   notifier_service:     azine_email.yourbundle.notifier_service			
```	

### user setup  

we need to edit Acme\SomeBundle\Entity\User to implement it .

do not forget your User Entity is fos_user instance 

change 
```
class User extends BaseUser 
{
```
to
```
class User extends BaseUser implements \Azine\EmailBundle\Entity\RecipientInterface
{

    /**
     * @ORM\Column(type="boolean",nullable=true)
     */
    protected $newsletter;
	
	/**
     * Set newsLetter
     *
     * @param boolean $newsLetter
     * @return User
     */
    public function setNewsletter($newsLetter)
    {
        $this->newsletter = $newsLetter;

        return $this;
    }
	
	public function getNewsletter()
    {
        return true;
        return $this->newsletter;
    }
    
    public function getDisplayName()
    {
        return $this->username;
    }
    public function getPreferredLocale()
    {
        return 'en'; // depends on you 
    }
    public function getNotificationMode()
    {
         return 2 ; // still don't know why we need this
    }
	
```		

update your schema then set some user newsLetter field to true . 

final concept is to defind templates !
you must be able to send newsletter using command witch is explain in readme file but we need to copy templates ,
please copy default base and layouts to app/Resources/AzineEmailBundle and we are Done !

if you run newsletter command you must see results .
