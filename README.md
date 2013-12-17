Azine Email Bundle
==================

Symfony2 Bundle provides an infrastructure for the following functionalities:

- simplify the rendering and sending of nicely styled html-emails from within your application
- send notifications/update-infos to your recipients, immediately or aggregated and scheduled.
- send newsletters to the recipients which wish to recieve it.
- view sent emails in a web-view in the browser, for the case the email isn't displayed well in the users email-client.

You can easily use it with transactional email services like mailgun.com.

[![Build Status](https://travis-ci.org/azine/email-bundle.png)](https://travis-ci.org/azine/email-bundle)
[![Total Downloads](https://poser.pugx.org/azine/email-bundle/downloads.png)](https://packagist.org/packages/azine/email-bundle)
[![Latest Stable Version](https://poser.pugx.org/azine/email-bundle/v/stable.png)](https://packagist.org/packages/azine/email-bundle)


## Requirements

##### 1. Swift-Mailer with working configuration 
Mails are sent using the Swiftmailer with it's configuration.

So the symfony/swiftmailer-bundle must be installed and properly configured.

https://github.com/symfony/SwiftmailerBundle

##### 2. Doctrine for notification spooling
For spooling notifications and for the web-view, Notification-/SentEmail-Objects 
(=&gt;`Azine\EmailBundle\Entity\Notification`/`Azine\EmailBundle\Entity\SentEmail` ) 
are stored in the database. So far only doctrine-storage is implemented.

##### 3. FOSUserBundle
In its current Version it depends on the [FOSUserBundle](https://github.com/FriendsOfSymfony/FOSUserBundle), 
as it also "beautyfies" the mails sent from the FOSUserBundle and uses the Users to 
provide recipient information (name/email/notification interval/newsletter subscription) 
for the mails to be sent.

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
   	new Azine\EmailBundle\EmailBundle(),
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

    # list of folders from which images are allowed to be embeded into emails
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
    web_view_service:     azine_email.example.web.view.service

```

## Customise the content and layout of your emails
You can/must customize the layout of your email in three ways:

- define your own styles by writing your own implementation of the TemplateProviderInterface 
- use your own images
- create your own html and txt twig-templates.

A general overview is given here and the classes you should extend contain 
more inline-documentation on how stuff works.


### Your own implementation of TemplateProviderInterface
This bundle includes a default implementation of a TemplateProvider ( =&gt; `Services/AzineTemplateProvider`) 
and also an example how to customize things (`Services/ExampleTemplateProvider`).

Remember that css-styles don't work in most email viewers. You need to 
embed everything into the attributes (e.g. "style") of your html-elements 
and do not use div-elements as they are not properly displayed in 
many viewers. Use Tables instead (&lt;table&gt;&lt;tr&gt;&lt;td&gt; etc.) 
to layout your emails.

e.g. &lt;table width="100%"&gt;&lt;tr&gt;&lt;td height="20" style="font-size: 12px; color: blue;"&gt;Bla bla&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;

You can define styles you would like to use in your emails and and also blocks of html-code. 
E.g. a drop-shadow implemented with td-elements with different shades of grey as background color. 
=&gt; see "leftShadow" and "cellSeparator" in the AzineTemplateProvider class. 


### Your own Images
You can use your own images which will be embeded into the emails. To do this, 
just define the path to your image-folder in your config.yml. =&gt; see above.

Some (web-) mail clients (gmail/thunderbird) will show those embeded images 
properly without aksing the recipient if he would like to show the attached images. 

BUT as far as I can tell, this only works if there is only one email recipient 
visible to the client and the "from"-address matches the account that the mails 
have been send from. There are many reasons why the images might not get displayed, 
so make sure your mails look good without the images too.


### Your own Twig-Templates
There are two kinds of templates required for this bundle, and both kinds for 
html- and for txt-content.


#### 1. the wrapper-templates
These templates contain a header-section (logo etc.), header-content-section 
("This is our newsletter blablabla"), main content (see "content-item-templates" 
below) and footer (links etc.).

They contain stuff that is usually exactly the same (except the greeting) for 
each of your recipients of this email. 

This bundle will render email that consist of a html-part and a plain-text part. 

The supplied baseEmailLayout-template in this bundle is split into two files 
`baseEmailLayout.txt.twig` and `baseEmailLayout.html.twig` to make them more 
easy to extend and manage. The *.txt.twig with the required blocks and the *.html.twig 
which is included in the body_html-block of the *.txt.twig template. 

The "*.txt.twig" is the template that will be called for rendering. It must 
contain the following blocks:

- subject
- body_txt
- body_html

#### 2. the content item-templates: 
In a newsletter- or notification-email you probably have different kind of 
items you would like to include. 

For example in an update-email to your users you could mention 6 private messages, 
3 events and 2 news-articles. 

For those three types of content items you can define different "content-item-templates" 
and also differenct styles.

An example for "Private-Messages" is included =&gt; `Resources/views/contentItem/message.txt.twig` 
and `Resources/views/contentItem/message.html.twig`.

For a type of content-item you must allways provide a html and a txt-version. They will 
be referenced by their full ID withour the format.twig-ending.

=&gt; "AzineEmailBundle:contentItem:message" for `Resources/views/contentItem/message.txt.twig` 
and `Resources/views/contentItem/message.html.twig`

When rendering those templates you have access to the styles and snippets defined 
for this template in your TemplateProvider.


## Make your emails available in the web-view 
In the "web-view" you can take a look at the rendered html-emails in your browser and 
send test-emails to your own email-address to view it in your favorite email-client.

Also users reading you email can take a look at the email in their browser, if their 
favorite email client messes up the layout. It adds a link at the top of you email
to direct your users to the web-view : 
"If this email isn't displayed properly, see the web-version"

### Configuring the web-view
In order to use the web-view you must implement your version of WebViewServiceInterface , 
configure it as service in your services.yml/.xml and set it in your config.yml 
as `azine_email_web_view_service`.

You can define how long those mails shall be kept available by setting the 
value for `azine_email_web_view_retention`. The default is 90 days.

The web-view uses the following routes that:

```
// ...EmailBundle/Resources/config/routing.yml
# route for users to see emails
azine_email_webview:
    pattern:  /email/webview/{token}
    defaults: { _controller: "AzineEmailBundle:AzineEmailTemplate:webView" }
    
# route for images that were embeded in emails and now must be shown in web-view
azine_email_serve_template_image:
    pattern:  /email/image/{folderKey}/{filename}
    defaults: { _controller: "AzineEmailBundle:AzineEmailTemplate:serveImage"}

# index with all the email-templates you configured in you implementation of WebViewServiceInterface
azine_email_template_index:
    pattern:  /admin/email/
    defaults: { _controller: "AzineEmailBundle:AzineEmailTemplate:index" }
    
# preview of a template filled with dummy-data ... this should probably only be accessible by admins
azine_email_web_preview:
    pattern:  /admin/email/webpreview/{template}/{format}
    defaults: { _controller: "AzineEmailBundle:AzineEmailTemplate:webPreView", format : null }

# route to send test-mails filled with dummy-data ... this should probably only be accessible by admins
azine_email_send_test_email:
    pattern:  /admin/email/send-test-email-for/{template}/to/{email}
    defaults: { _controller: "AzineEmailBundle:AzineEmailTemplate:sendTestEmail", email: null}
```   

To use web-view you must enable these routes by including the routing file in you config.

```
// app/config/routing.yml
...
azine_email_bundle:
    resource: "@AzineEmailBundle/Resources/config/routing.yml"
    prefix:   /{_locale}/
    requirements:
        culture:  en|de
...
```

#### Implement WebViewServiceInterface
The easiest way for you is to extend the `AzineWebViewService` and implement the three public functions
- `public function getTemplatesForWebPreView()`
- `public function getTestMailAccounts()`
- `public function getDummyVarsFor($template, $locale)`
and maybe the 
- `public function __constructor(...)` if you need any extra services to gather the dummy-data

You can take a look at the `ExampleWebViewService` what to do in those functions.

#### Update your database
The web-view stores all sent emails in the database. In order to do so, the entity 
SentEmail must be available.

It is defined in `SentEmail.orm.yml` and you can update you database either with 
the command `doctrine:schema:update` or via migrations.

#### Define which mails to store for web-view
You can decide which mails you want to make available in web-view by overriding 
the function `saveWebViewFor($template)` in your TemplateProvider.

See ExampleTemplateProvider for hints on how to do this.

#### Deleting old "SentEmails"
The symfony console-command `emails:remove-old-web-view-emails` will remove all "SentEmail" 
that are older than the number of days you defined in `azine_email_web_view_retention`.

You can configure a cron-job to call this command.

#### Deleting failed mail-files from spool-folder
Some emails you want to send might fail and if you configured the swiftmailer to use file spooling,
the spool-files for these mails will stay in you spool-folder named `*.message.sending`.

Running the `swiftmailer:spool:send` command will try to send those files again, but if sending
fails repeatedly, you might end up with a spool-folder filled with mail-files that will allways fail.

To delete those files, you can use the `emails:clear-and-log-failures` command from this package.

Add a cron-job to do this for your application once per day, or if you have a lot of failing messages,
once per hour.

Using the `date` parameter for the command, you can define the duration during which the
mailer should attempt to send those mails, before they are deleted by this command.

## TWIG-Filter textWrap
This bundle also adds a twig filter that allows you to wrap text using the php 
function wordwrap. It defaults to a line width of 75 chars.

```
{{ "This text should be wrapped after 75 characters, as it is too long for just one line. But there is not line break in the text so far" | textWrap }}
or
{{ "This text should be wrapped after 30 characters, as it is too long for just one line. But there is not line break in the text so far" | textWrap(30) }}
```


## Use transactional email services e.g.e mailgun.com
To send and track your emails with a transactional email service  
like mailgun.com, postmarkapp.com or madrill.com, you can set 
the swiftmailer configuration to use their smpt server to send
emails.

```
// app/config/config.yml (or imported from your parameters.yml.dist)
swiftmailer:
    host:           "smtp.mailgun.org"
    username:       "postmaster@acme.com"
    password:       "your-secret-mailgun.com-password"
    transport:      "smtp"
    port:           "587"
    encryption:     "tls"
```

PS: make sure your application is allowed to connect to the other 
smpt. This might be blocked on shared hosting accounts. => ask 
your admin to un-block it.


