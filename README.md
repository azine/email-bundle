Azine Email Bundle
==================

Symfony2 Bundle provides the following functionalities:

- simplify the rendering and sending of nicely styled html-emails from within your application
- provide an infrastructure to send notifications/update-infos to your recipients, immediately or aggregated and scheduled.
- provide an infrastructure to send newsletters to the recipients which wish to recieve it.

## Requirements

##### 1. Swift-Mailer with working configuration 
Mails are sent using the Swiftmailer with it's configuration.

So the symfony/swiftmailer-bundle must be installed and properly configured.

https://github.com/symfony/SwiftmailerBundle

##### 2. Doctrine for notification spooling
For spooling notifications, Notification-Objects (=&gt; Entity\Notification.php ) are stored in the database. So far only doctrine-storage is implemented.

##### 3. FOSUserBundle
In its current Version it depends on the FOSUserBundle, as it also "beautyfies" the mails sent from the 
FOSUserBundle and uses the Users to provide recipient information (name/email/notification interval/newsletter subscription) 
for the mails to be sent.


## Installation
To install AzineGeoBlockingBundle with Composer just add the following to your composer.json file:

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

Then, you can install the new dependencies by running Composer’s update command from the directory where your composer.json file is located:

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


## Configuration options
For the bundle to work with the default-settings, no config-options are required, but the swiftmailer must be configured.

This is the complete list of configuration options with their defaults.
```
// app/config/config.yml
azine_email:

    # the class of your implementation of the RecipientInterface
    recipient_class:      Acme\SomeBundle\Entity\User # Required

    # the fieldname of the boolean field on the recipient class indicating, that a newsletter should be sent or not
    recipient_newsletter_field:  news_letter

    # the service-id of your implementation of the nofitier service to be used
    notifier_service:     azine_email.example.notifier_service

    # the service-id of your implementation of the template provider service to be used
    template_provider:    azine_email.example.template_provider # Required

    # the service-id of the implementation of the RecipientProviderInterface to be used
    recipient_provider:   azine_email.default.recipient_provider

    # the service-id of the mailer service to be used
    template_twig_swift_mailer:  azine_email.default.template_twig_swift_mailer
    no_reply:

        # the no-reply email-address
        email:                no-reply@example.com # Required

        # the name to appear with the 'no-reply'-address.
        name:                 notification daemon # Required

    # absolute path to the image-folder containing the images used in your templates.
    image_dir:            %kernel.root_dir%/../vendor/azine/email-bundle/Azine/EmailBundle/Resources/htmlTemplateImages/

```

## Customise the content and layout of your emails
You can/must customize the layout of your email in three ways:
- define your own styles by writing your own implementation of the TemplateProviderInterface 
- use your own images
- create your own html and txt twig-templates.

A general overview is given here and the classes you should extend contain more inline-documentation on how stuff works.

### Your own implementation of TemplateProviderInterface
This bundle includes a default implementation of a TemplateProvider ( =&gt; AzineTemplateProvider) 
and also an example how to customize things (ExampleTemplateProvider).

Remember that css-styles don't work in most email viewers. You need to embed everything into the attributes (e.g. "style") of 
your html-elements and do not use div-elements as they are not properly displayed in many viewers. 
Use Tables instead (&lt;table&gt;&lt;tr&gt;&lt;td&gt;''' etc.) to layout your emails.

e.g. &lt;table width="100%"&gt;&lt;tr&gt;&lt;td height="20" style="font-size: 12px; color: blue;"&gt;Bla bla&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;

You can define styles you would like to use in your emails and and also blocks of html-code. 
E.g. a drop-shadow implemented with td-elements with different shades of grey as background color. 
=&gt; see "leftShadow" and "cellSeparator" in the AzineTemplateProvider class. 


### Your own Images
You can use your own images which will be embeded into the emails. To do this, just define the path to your image-folder in your config.yml. =&gt; see above.

Some (web-) mail clients (gmail/thunderbird) will show those embeded images properly without aksing the recipient if he would like to show the attached images. 

BUT as far as I can tell, this only works if there is only one email recipient visible to the client and the "from"-address matches the account that the mails have been send from. There are many reasons why the images might not get displayed, so make sure your mails look good without the images too.

### Your own Twig-Templates
There are two kinds of templates required for this bundle, and both kinds for html- and for txt-content.

#### 1. the wrapper-templates
These templates contain a header-section (logo etc.), header-content-section ("This is our newsletter blablabla"), main content (see "content-item-templates" below) and footer (links etc.).

They contain stuff that is usually exactly the same (except the greeting) for each of your recipients of this email. 

This bundle will render email that consist of a html-part and a plain-text part. 

The supplied baseEmailLayout-template in this bundle is split into two files "baseEmailLayout.txt.twig" and "baseEmailLayout.html.twig" to make them more easy to extend and manage. The *.txt.twig with the required blocks and the *.html.twig which is included in the body_html-block of the *.txt.twig template. 

The "*.txt.twig" is the template that will be called for rendering. It must contain the following blocks:

- subject
- body_txt
- body_html

#### 2. the content item-templates: 
In a newsletter- or notification-email you probably have different kind of items you would like to include. 

For example in an update-email to your users you could mention 6 private messages, 3 events and 2 news-articles. 

For those three types of content items you can define different "content-item-templates" and also differenct styles.

An example for "Private-Messages" is included =&gt; 'Resources/views/contentItem/message.txt.twig' and 'Resources/views/contentItem/message.html.twig'.

For a type of content-item you must allways provide a html and a txt-version. They will be referenced by their full ID withour the format.twig-ending.

=&gt; AzineEmailBundle:contentItem:message for 'Resources/views/contentItem/message.txt.twig' and 'Resources/views/contentItem/message.html.twig'

When rendering those templates you have access to the styles and snippets defined for this template in your TemplateProvider.


## TWIG-Filter textWrap
This bundle also adds a twig filter that allows you to wrap text using the php function wordwrap. It defaults to a line width of 75 chars.

'''
{{ "This text should be wrapped after 75 characters, as it is too long for just one line. But there is not line break in the text so far" | textWrap }}
or
{{ "This text should be wrapped after 30 characters, as it is too long for just one line. But there is not line break in the text so far" | textWrap(30) }}
'''


## Open Issues
- I'd like to remove the dependency to the FOSUserBundle, but as I work with the FOSUserBundle, this has no priority for me. If you would like to see this implemented, let me know.
