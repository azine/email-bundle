Azine Email Bundle Quick-Start
==============================

On this page you find the minimal required steps to get up and running with AzineEmailBundle. 

The steps are as follows (some described in this document, some are in the [README.md](README.md)) :

- install AzineEmailBundle: see ["Installation"-Section in the README.md](README.md#installation)
- update your User-Class: see [ExampleUser](Entity/ExampleUser.php)
- implement a RecipientProvider or use the provided default-provider: see [AzineRecipientProvider](Services/AzineRecipientProvider)
- implement a NotifierService: see [below](#implement-a-notifierservice-required)
- implement a TemplateProvider: see [below](#implement-a-templateprovider-optional-required-if-you-adduse-your-own-templates)
- implement a WebViewService: see [below](#implement-a-webviewservice-optional)
- update your config.yml & services.yml: see [below](#configuration-options-required)
- design your email templates: see [Your own Twig-Templates](README.md#your-own-twig-templates)
- define the content of your Newsletters: see [Customise the content of you emails](README.md#customise-the-content-of-you-emails)

and optionally:
- send you mails through the the transactional email services mailgun.com. See [Use transactional email services e.g.e mailgun.com](README.md#use-transactional-email-services-ege-mailguncom)

For most things you'll have to do, there are Example*-classes and Example*.twig-templates with lots of comments in them on what has to be done in 
order to use the functionality.

## Update your User-Class (required)
The AzineEmailBundle works with "[Recipients](Entity/RecipientInterface.php)" so you can use your existing 
User-class if you like.

If you are sending emails to your "Users", you can just update your Entity\User class by implementing the 
`RecipientInterface`. 

See the `ExampleUser` for an example implementation. Remember to update your database and set the "newsletter-field"
of some users to true for testing.

## Implement a RecipientProvider (optional if you use the FosUserBundle)
To find out which users should receive a newsletter, we need a "[RecipientProvider](Services/RecipientProviderInterface.php)".

If you work with the FosUserBundle, you can use the "default implementation" 
(=> [AzineRecipientProvider.php](Services/AzineRecipientProvider.php) from the AzineEmailBundle.

## Implement a NotifierService (required)
Sending a Newsletter with "default content" defined by this bundle does not make any sense! So in your implementation
of the [NotifierService](Services/NotifierServiceInterface.php), you define what is going into your newsletter 
and notifications.

The easiest way to do this, is to create a subclass of the [AzineNotifierService](Services/AzineNotifierService.php). 
Take a look at the first 8 functions and the constructor and the comments for these functions. You will want to override
these 8 functions.

Also take a look at the comments and the examples in [ExampleNotifierService](Services/ExampleNotifierService.php).

## Implement a TemplateProvider (optional, required if you add/use your own templates)
To be able to send your emails with your custom templates you need to implement a [TemplateProviderInterface](Services/TemplateProviderInterface.php).

Take a look at the comments and code in [ExampleTemplateProvider](Services/ExampleTemplateProvider.php) and in 
the [AzineTemplateProvider](Services/AzineTemplateProvider.php) to get an idea what you should do in your TemplateProvider.

For the templates you defined, you must also define if the emails sent with those templates should be 
available in the WebView.

## Implement a WebViewService (optional)
AzineEmailBundle can store the sent emails in the database, so the user can take a look at them in her 
browser, if the email-client does not display the emails nicely.

And also as administrator you might want to preview how an email looks like in different email-clients, 
before sending the emails to your users.

To send those test-emails, you need to provide "dummy"-data for your templates and for your content-items.
You can/must define this "dummy"-data in this service. See the getDummyVarsFor-function.

In your implementation of the [WebViewServiceInterface](Services/WebViewServiceInterface.php) you define 
all this. Again it is easiest to create a subclass of the "default implementation" ([AzineWebViewService](Services/AzineWebViewService.php)). 

Check out the [ExampleWebViewService](Services/ExampleWebViewService.php) to see an example.

## Configuration options (required)
Update your services.yml to define your implementations of the services mentioned above (RecipientProvider, NotifierService, TemplateProvider, WebViewService).
```yml
# src/Your/AwesomeBundle/Resources/config/services.yml

    # the notifierservice is required, as you MUST provide your own implementation
    your.awesome.bundle.notifierservice:
        class: Your\AwesomeBundle\Services\YourAwesomeNotifierService
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
            
    # if you don't add new templates, the template_provider is optional
    # this is just an example! Your implementation might need other parameters.
    your.awesome.bundle.template_provider:
        class: Your\AwesomeBundle\Services\YourAwesomeTemplateProvider
        arguments:
          router:       "@router"
          translator:   "@translator.default"
          parameters:
            image_dir:  "%azine_email_image_dir%"
            allowed_images_folders: "%azine_email_allowed_images_folders%"
            tracking_params_campaign_name: "%azine_email_tracking_params_campaign_name%"
            tracking_params_campaign_term: "%azine_email_tracking_params_campaign_term%"
            tracking_params_campaign_content: "%azine_email_tracking_params_campaign_content%"
            tracking_params_campaign_medium: "%azine_email_tracking_params_campaign_medium%"
            tracking_params_campaign_source: "%azine_email_tracking_params_campaign_source%"

    # if you don't add new templates and your newsletter does not need any dummy-data
    # then the web_view_service is optional
    # this is just an example! Your implementation might need other parameters.
    your.awesome.bundle.web_view_service:
        class: Your\AwesomeBundle\Services\YourAwesomeWebViewService
        arguments:
          router:       "@router"


```

For the bundle to work with the "default-settings", only a few config-options are absolutely required:

```yml
# app/config/config.yml
azine_email:

    # the class of your implementation of the RecipientInterface
    recipient_class:      Your\AwesomeBundle\Entity\User 

    # the service-id of your implementation of the nofitier service to be used
    notifier_service:     your.awesome.bundle.notifierservice 

    # the service-id of your implementation of the template provider service to be used
    template_provider:    your.awesome.bundle.template_provider 

    # the service-id of your implementation of the web view service to be used
    web_view_service:     your.awesome.bundle.web_view_service

    # the no-reply email-address
    no_reply:             # Required
        email:                no-reply@example.com # Required
        name:                 notification daemon # Required

```

