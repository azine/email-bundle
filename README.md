Azine Email Bundle
==================

Symfony2 Bundle provides an infrastructure for the following functionalities:

- simplify the rendering and sending of nicely styled html-emails from within your application
- send notifications/update-infos to your recipients, immediately or aggregated and scheduled.
- send newsletters to the recipients which wish to recieve it.
- preview the email-rendering in your browser and sent test mails to you, to view in your favorite email client.
- view sent emails in a web-view in the browser, for the case the email isn't displayed well in the users email-client.
- link campaign tracking with your analytics tool (google analytics or piwik or ...)
- track email opens with your analytics tool (google analytics or piwik or ...)
- works nicely with transactional email services like mailgun.com.

## Table of contents
* [Quick start guide &amp; examples](#quick-start-guide--examples)
* [Requirements](#requirements)
      * [1. Swift-Mailer with working configuration](#1-swift-mailer-with-working-configuration)
      * [2. Doctrine for notification spooling](#2-doctrine-for-notification-spooling)
      * [3. FOSUserBundle](#3-fosuserbundle)
* [Installation](#installation)
* [Configuration options](#configuration-options)
* [Customise the content and subjects of your emails](#customise-the-content-and-subjects-of-your-emails)
* [Customise the layout of your emails](#customise-the-layout-of-your-emails)
  * [Your own implementation of TemplateProviderInterface](#your-own-implementation-of-templateproviderinterface)
  * [Your own Images](#your-own-images)
  * [Your own Twig-Templates](#your-own-twig-templates)
    * [1. the wrapper-templates](#1-the-wrapper-templates)
    * [2. the content item-templates](#2-the-content-item-templates)
* [Make your emails available in the web-view and web-pre-view](#make-your-emails-available-in-the-web-view-and-web-pre-view)
  * [Configuring the web-view and web-pre-view](#configuring-the-web-view-and-web-pre-view)
  * [Implement WebViewServiceInterface](#implement-webviewserviceinterface)
  * [Update your database](#update-your-database)
  * [Define which mails to store for web-view](#define-which-mails-to-store-for-web-view)
  * [Deleting old "SentEmails"](#deleting-old-sentemails)
* [Operations](#operations)
  * [Deleting failed mail-files from spool-folder](#deleting-failed-mail-files-from-spool-folder)
  * [Examples of Cron-Jobs we use in operation.](#examples-of-cron-jobs-we-use-in-operation)
* [TWIG-Filters](#twig-filters)
  * [textWrap](#textwrap)
  * [urlEncodeText](#urlencodetext)
  * [stripAndConvertTags](#stripandconverttags)
  * [addCampaignParamsForTemplate](#addcampaignparamsfortemplate)
* [Use two different mailers for "normal" and for "urgent" emails](#use-two-different-mailers-for-normal-and-for-urgent-emails)
* [Use transactional email services e.g.e mailgun.com](#use-transactional-email-services-ege-mailguncom)
* [GoogleAnalytics &amp; Piwik: Customize the tracking values in email links](#googleanalytics--piwik-customize-the-tracking-values-in-email-links)
* [Email-open-tracking with a tracking image (e.g. with piwik or google-analytics)](#email-open-tracking-with-a-tracking-image-eg-with-piwik-or-google-analytics)
* [Build-Status etc.](#build-status-etc)



## Quick start guide & examples
- [How to configure](QUICK_START.md)
- [Examples on how to use the plugin](USAGE_EXAMPLES.md)

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
To install AzineEmailBundle with Composer just add the following to your `composer.json` file:

```javascript
// composer.json
{
    require: {
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

```php
<?php

// in AppKernel::registerBundles()
$bundles = array(
    // ...
    new Azine\EmailBundle\AzineEmailBundle(),
    // ...
);
```

Register the routes of the AzineEmailBundle:

```yml
# in app/config/routing.yml

azine_email_bundle:
    resource: "@AzineEmailBundle/Resources/config/routing.yml"
    
```

## Configuration options
For the bundle to work with the default-settings, no config-options are 
required, but the swiftmailer must be configured.

This is the complete list of configuration options with their defaults.
```yml
# app/config/config.yml
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
        name:                 'notification daemon' # Required

    # absolute path to the image-folder containing the images used in your templates.
    image_dir:            '%kernel.root_dir%/../vendor/azine/email-bundle/Azine/EmailBundle/Resources/htmlTemplateImages/'

    # list of folders from which images are allowed to be embeded into emails
    allowed_images_folders:  []

    # newsletter configuration
    newsletter:

        # number of days between newsletters
        interval:             '14'

        # time of the day, when newsletters should be sent, 24h-format => e.g. 23:59
        send_time:            '10:00'

    # templates configuration
    templates:

        # wrapper template id (without ending) for the newsletter
        newsletter:           'AzineEmailBundle::newsletterEmailLayout'

        # wrapper template id (without ending) for notifications
        notifications:        'AzineEmailBundle::notificationsEmailLayout'

        # template id (without ending) for notification content items
        content_item:         'AzineEmailBundle:contentItem:message'

    # the parameters for link tracking. see https://support.google.com/analytics/answer/1033867 for more infos.
    tracking_params_campaign_name:    utm_campaign #defaluts to utm_name, piwik and google analytics both understand these parameters
    tracking_params_campaign_term:    utm_term     #defaluts to utm_term, piwik and google analytics both understand these parameters
    tracking_params_campaign_content: utm_content  #defaluts to utm_content, piwik and google analytics both understand these parameters
    tracking_params_campaign_medium:  utm_medium   #defaluts to utm_medium, piwik and google analytics both understand these parameters
    tracking_params_campaign_source:  utm_source   #defaluts to utm_source, piwik and google analytics both understand these parameters

    # See the chapter further below for more information
    email_open_tracking_url:  null

    # Defaults to the AzineEmailOpenTrackingCodeBuilder. Depending on the email_open_tracking_url it will create tracking images for piwik or google analytics. 
    email_open_tracking_code_builder:  azine.email.open.tracking.code.builder.ga.or.piwik

    # number of days that emails should be available in web-view
    web_view_retention:   90

    # the service-id of your implementation of the web view service to be used
    web_view_service:     azine_email.example.web_view_service

```

## Customise the content and subjects of your emails
You must implement your version of the notifier service in which you pull the content
of you notification- or newsletter-emails together. In you subclass of AzineNotifierService you can/should implement
the following functions:

- getVarsForNotificationsEmail => variables you use in your twig-templates that are the same for all notification recipients
- getRecipientVarsForNotificationsEmail => variables you use in twig-templates for notifications that are specific for a recipient
- getRecipientSpecificNotificationsSubject => the notification-email subject
- getGeneralVarsForNewsletter => variable you use in twig-templates that are the same for all newsletter recipients
- getNonRecipientSpecificNewsletterContentItems => content items that are the same for all newsletter recipients
- getRecipientSpecificNewsletterParams => variables you use in twig-templates that are specific for a recipient
- getRecipientSpecificNewsletterContentItems => content items for a specific recipient
- getRecipientSpecificNewsletterSubject => newsletter subject for a specific recipient

See `ExampleNotifierService.php` for an example.

## Customise the layout of your emails
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

#### 2. the content item-templates
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


## Make your emails available in the web-view and web-pre-view
In the "web-pre-view" you can take a look at the content and layout of your email 
before sending them and you can send test-emails to your own email-address to view 
it in your favorite email-client.

In the "web-view" users reading you email can take a look at the email in their 
browser, if their favorite email client messes up the layout. The bundle adds a 
link at the top of you email to direct your users to the web-view : 
"If this email isn't displayed properly, see the web-version"

### Configuring the web-view and web-pre-view
In order to use the web-view you must:
1) implement your version of `WebViewServiceInterface`. To minimize your effort, you can subclass the `AzineWebViewService`.
2) configure it as service in your `services.yml` and 
3) set it in your `config.yml` as `azine_email_web_view_service`.

You can define how many days the sent mails shall be kept available by 
setting the value for `azine_email_web_view_retention`. The default is 90 days.

The web-view uses the following routes:
```yml
// ...EmailBundle/Resources/config/routing.yml
# route for users to see an emails
azine_email_webview:
    pattern:  /email/webview/{token}
    defaults: { _controller: "AzineEmailBundle:AzineEmailTemplate:webView" }
    
# route for images that were embeded in emails and now must be shown in web-view
azine_email_serve_template_image:
    pattern:  /email/image/{folderKey}/{filename}
    defaults: { _controller: "AzineEmailBundle:AzineEmailTemplate:serveImage"}

# index with all the email-templates you configured in you implementation of `WebViewServiceInterface`
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

```yml
// app/config/routing.yml
...
azine_email_bundle:
    resource: "@AzineEmailBundle/Resources/config/routing.yml"
    prefix:   /{_locale}/
    requirements:
        culture:  en|de
...
```

### Implement WebViewServiceInterface
The easiest way for you is to extend the `AzineWebViewService` and implement the three public functions
- `public function getTemplatesForWebPreView()`
- `public function getTestMailAccounts()`
- `public function getDummyVarsFor($template, $locale)`
and maybe the 
- `public function __constructor(...)` if you need any extra services to gather the dummy-data to populate the web-pre-view.

You can take a look at the `ExampleWebViewService` what to do in those functions.

### Update your database
The web-view stores all sent emails in the database. In order to do so, the entity 
`SentEmail` must be available.

It is defined in `SentEmail.orm.yml` and you can update you database either with 
the command `doctrine:schema:update` or via migrations.

### Define which mails to store for web-view
You can decide which mails you want to make available in web-view by overriding 
the function `saveWebViewFor($template)` in your TemplateProvider.

See `ExampleTemplateProvider` for hints on how to do this.

### Deleting old "SentEmails"
Sent emails that are available in the web-view are stored in the database. 
As you want to get rid of old emails, there is a Symfony command to handle 
this for you. 

The symfony console-command `emails:remove-old-web-view-emails` will remove all "SentEmail" 
that are older than the number of days you defined in `azine_email_web_view_retention` in
your `config.yml`.

You can configure a cron-job to call this command regularly. See [Cron-Job examples](#cron-job-examples) below.

## Operations 
### Deleting failed mail-files from spool-folder
Some emails you want to send might fail and if you configured the swiftmailer to use file spooling,
the spool-files for these mails will stay in you spool-folder named `*.message.sending`.

Running the `swiftmailer:spool:send` command will try to send those files again, but if sending
fails repeatedly, you might end up with a spool-folder filled with mail-files that will allways fail.

To delete those files, you can use the `emails:clear-and-log-failures` command from this package.

Add a cron-job to do this for your application once per day, or if you have a lot of failing messages,
once per hour. See [Cron-Job examples](#cron-job-examples) below.

Using the `date` parameter for the command, you can define the duration during which the
mailer should attempt to send those mails, before they are deleted by this command.

### Examples of Cron-Jobs we use in operation.
Here are some examples how to configure your cronjobs to send the emails and cleanup periodically.

```bash
# Send a newsletter every friday:
0 	10 	* 	* 	5 	/usr/local/bin/php -c /path/to/folder/with/php.ini-file/to/use /path/to/your/application/app/console emails:sendNewsletter -e prod >>/path/to/your/application/app/logs/cron.log 2>&1 

# Send a newsletter every other friday:
0 	10 	* 	* 	5 	[ `expr \`date +\%s\` / 86400 \% 2` -eq 0 ] && /usr/local/bin/php -c /path/to/folder/with/php.ini-file/to/use /path/to/your/application/app/console emails:sendNewsletter -e prod >>/path/to/your/application/app/logs/cron.log 2>&1 

# Send notifications every minute:
# Beware! If processing this command takes longer than 1 minute (e.g. trying to send a lot of notifications in one run), 
# then duplicate emails will be sent, as another run is started every 60s. This issue is fixed in the master branch, but only available for Symfony 2.6
* 	* 	* 	* 	* 	/usr/local/bin/php -c /path/to/folder/with/php.ini-file/to/use /path/to/your/application/app/console emails:sendNotifications -e prod >>/path/to/your/application/app/logs/cron.log 2>&1 

# Delete old web-view-emails every night:
0 	3 	1 	* 	* 	/usr/local/bin/php -c /path/to/folder/with/php.ini-file/to/use /path/to/your/application/app/console emails:remove-old-web-view-emails -e prod >>/path/to/your/application/app/logs/cron.log 2>&1 

# Try to re-send failed messages every night:
1 	4 	* 	* 	* 	/usr/local/bin/php -c /path/to/folder/with/php.ini-file/to/use /path/to/your/application/app/console emails:clear-and-log-failures -e prod >>/path/to/your/application/app/logs/cron.log 2>&1 

# If you are spooling emails, then call the send command of the swiftmailer every minute:
* 	* 	* 	* 	* 	/usr/local/bin/php -c /path/to/folder/with/php.ini-file/to/use /path/to/your/application/app/console swiftmailer:spool:send --env=prod >>/path/to/your/application/app/logs/cron.log 2>&1 

```

## TWIG-Filters 
This bundle also adds some twig filters that are useful when writing emails. 

### textWrap
The `textWrap` filter allows you to wrap text using the php function wordwrap. 
It defaults to a line width of 75 chars.

```twig
{{ "This text should be wrapped after 75 characters, as it is too long for just one line. But there is not line break in the text so far" | textWrap }}
or
{{ "This text should be wrapped after 30 characters, as it is too long for just one line. But there is not line break in the text so far" | textWrap(30) }}
```
### urlEncodeText
This filter will url encode text. With url encoded text, you can, for example, create mailto-links that will create an email with the subject and body already pre-filled.

```twig
{% set subject = "I love your service. Thanx a lot" | trans | urlEncodeText %}
{% set body = "Hi,\n\nI just wanted to say thank you!\n\nBest regards,\n%username%" | trans({'%username%' : user.name}) | urlEncodeText %}
<a href="mailto:support@azine.com?subject={{ subject }}&body={{ body }}">Mail to us</a>
```
### stripAndConvertTags
When writing html emails, you should always supply a reasonably similar text version of your email. 

If you do not have a text version of your html content, you can convert the html-code into something acceptable with this filter.

```twig
// some.email.txt.twig
{{ htmlContent | stripAndConvertTags }}
```
This will:
- replace all "a" html elements with text built as follows: "link text: url" or just "url", depending on the link text.
- strip all html tags => see [strip_tags](http://php.net/manual/function.strip-tags.php)
- replace htmlentites with their original character => see [html_entity_decode](http://php.net/manual/function.html-entity-decode.php)

### addCampaignParamsForTemplate
This filter will get the tracking campaign parameters and values for the given twig-template and merge them with the given campaign parameters. 
Then all links in the template will be complemented with the campaign parameters that are not yet present.

```twig
// used for examlpe in Azine/EmailBundle/Resources/views/baseEmailLayout.html.twig
{% filter addCampaignParamsForTemplate(contentItemTemplate, contentItemParams) %}
    {% include contentItemTemplate ~ '.html.twig' with contentItemParams %}
{% endfilter %}
```

## Use two different mailers for "normal" and for "urgent" emails
In most cases you'll probably prefer UI-performance over speed of email-delivery. But for example for the password-reset- or for email-confirmation-emails
you want the user to receive the mail a.s.a.p and not after the next spool-mailing.

To achieve this you must do two things:
  1) configure multiple swiftmailers in you config.yml-files (see example below and http://symfony.com/doc/2.6/reference/configuration/swiftmailer.html#using-multiple-mailers)
  2) define which emails should be delivered immediately in your TemplateProvider.

Here are extracts from config.yml-files:
```yml
# app/config/config.yml
# Swiftmailer Configuration
swiftmailer:
    default_mailer: defaultMailer // name of the default mailer defined below.
    mailers:
        defaultMailer: // you can choose your name for the default mailer
            host:           "%mailer_host%"
            username:       "%mailer_user%"
            password:       "%mailer_password%"
            transport:      "%mailer_transport%"
            port:           "%mailer_port%"
            encryption:     "%mailer_encryption%"
            antiflood:
                threshold:  10
                sleep:      2
            logging:        "%kernel.debug%"

        immediateMailer: // this name is hard-coded in the bundle!
            host:           "%mailer_host%"
            username:       "%mailer_user%"
            password:       "%mailer_password%"
            transport:      "%mailer_transport%"
            port:           "%mailer_port%"
            encryption:     "%mailer_encryption%"
            antiflood:
                threshold:  10
                sleep:      2
            logging:        "%kernel.debug%"


// app/config/config_prod.yml
// for most mails (defaultMailer) use spooling to improve ui responsiveness
// you must configure a cron-job to execute the "swiftmailer:spool:send"-command
swiftmailer:
    mailers:
        defaultMailer:
            spool:
                type: file
                path: "%kernel.root_dir%/spool.mails/prod"


// app/config/config_dev.yml
// make sure dev-environment mails are sent immediately to a developer-account and not to real email-addresses
swiftmailer:
    mailers:
        defaultMailer:
            delivery_address: mail-to-dev-account-spool@your.domain.com

        immediateMailer:
            delivery_address: mail-to-dev-account-nospool@your.domain.com


// app/config/config_test.yml
// don't send mails during test-runs, only spool them in a dedicated directory
swiftmailer:
    mailers:
        defaultMailer:
            spool:
                type: file
                path: "%kernel.root_dir%/spool.mails/test"

        immediateMailer:
            spool:
                type: file
                path: "%kernel.root_dir%/spool.mails/test"

```

And in your implementation of the TemplateProviderInterface, you can define emails from which templates should be sent immediately instead of spooled:
```php
// see ExampleTemplateProvider or AzineTemplateProvider for an example
   protected function getParamArrayFor($template){

     ...
        // send some mails immediately instead of spooled
        if($template == self::VIP_INFO_MAIL_TEMPLATE){
            $newVars[self::SEND_IMMEDIATELY_FLAG] = true;
        }
     ...

```


## Use transactional email services e.g.e mailgun.com
To send and track your emails with a transactional email service  
like mailgun.com, postmarkapp.com or madrill.com, you can set 
the swiftmailer configuration to use their smpt server to send
emails.

```yml
# app/config/config.yml (or imported from your parameters.yml.dist)
swiftmailer:
    host:           "smtp.mailgun.org"
    username:       "postmaster@acme.com"
    password:       "your-secret-mailgun.com-password"
    transport:      "smtp"
    port:           "587"
    encryption:     "tls"
```

If you are sending mails with mailgun on a free account and would like
to be able to check the logs etc. for more than only two days, also check
out the AzineMailgunWebhooksBundle. Mailgun deletes logs etc. from free
accounts after aprox. two days. With this bundle you can store the events
in your own database and keep them as long as you like. 

PS: make sure your application is allowed to connect to the other 
smpt. This might be blocked on shared hosting accounts. => ask 
your admin to un-block it.

## GoogleAnalytics & Piwik: Customize the tracking values in email links
In your implementation of the TemplateProvider, you can implement the 
function `getCampaignParamsFor($templateId, $params)` to define the 
values for the campaign tracking parameters in the links inside your 
emails.
 
You can define the values for each tracking parameter on an email 
template level and for emails that are composed from multiple nested 
content items, you can define values for these content item templates 
as well. And finally inside all the templates you can add campaign 
parameter values manually to individual links.

In this hierarchy (email template > content item template > individual link) 
values are not overwritten if they are defined on the more granular level.
 
You can check the resulting links in the WebPreView of the email.

## Email-open-tracking with a tracking image (e.g. with piwik or google-analytics)
To be able to track with Piwik or GoogleAnalytics (or the like) if an email 
has been opened, you can specify an image tracking url in your configuration.

```yml
#app/config/config.yml
azine_email:
  # for GoogleAnalytics
  email_open_tracking_url: "https://www.google-analytics.com/collect?v=1&..." 

  # for Piwik
  email_open_tracking_url: "https://your.host.com/piwik-directory/piwik.php?idsite={$IDSITE}&bots=1&rec=1"
```

If you configure the `email_open_tracking_url` in your config.yml, then the 
provided url will be complemented with the tracking parameters and values and 
a html img tag will be inserted into the html code at the end of your email.
 
If you wan't to change the way the tracking image url is complemented with
the tracking parameters and values, then you can create and use your own 
implementation of the EmailOpenTrackingCodeBuilderInterface and update your
config.yml to use that implementation.

```yml
// app/config/config.yml
azine_email:
  # Defaults to the AzineEmailOpenTrackingCodeBuilder. See the README.md file for more information
  email_open_tracking_code_builder:  your.email.open.tracking.code.builder
```                 

See these links for more details on email tracking with images:
- GoogleAnalytics: https://developers.google.com/analytics/devguides/collection/protocol/v1/email
- Piwik: http://piwik.org/docs/tracking-api/#image-tracker-code
- Blogpost: http://dyn.com/blog/tracking-email-opens-via-google-analytics/

The tracking-image will also be inserted in the WebPreView of your emails,
but to avoid false tracking events, the url will be modified to not
point to your email-open-tracking system.

## Build-Status etc.
[![Build Status](https://travis-ci.org/azine/email-bundle.png)](https://travis-ci.org/azine/email-bundle) [![Total Downloads](https://poser.pugx.org/azine/email-bundle/downloads.png)](https://packagist.org/packages/azine/email-bundle) [![Latest Stable Version](https://poser.pugx.org/azine/email-bundle/v/stable.png)](https://packagist.org/packages/azine/email-bundle) [![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/azine/email-bundle/badges/quality-score.png?s=6190311a47fa9ab8cfb45bfce5c5dcc49fc75256)](https://scrutinizer-ci.com/g/azine/email-bundle/) [![Code Coverage](https://scrutinizer-ci.com/g/azine/email-bundle/badges/coverage.png?s=57b026ec89fdc0767c1255c4a23b9e87a337a205)](https://scrutinizer-ci.com/g/azine/email-bundle/) [![Dependency Status](https://www.versioneye.com/user/projects/567eae02eb4f470030000001/badge.svg?style=flat)](https://www.versioneye.com/user/projects/567eae02eb4f470030000001) 
