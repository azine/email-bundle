Usage Examples
==============
Here are some usage examples for this bundle. Please also take a look at the example-implementations of some classes you could/should extend when you use this bundle.
- [ExampleUser](Entity/ExampleUser.php) & [ExampleUserRepository](Entity/Repositories/ExampleUserRepository.php)
- [ExampleNotifierService](Services/ExampleNotifierService.php)
- [ExampleTemplateProvider](Services/ExampleTemplateProvider.php)
- [ExampleWebViewService](Services/ExampleWebViewService.php)
- [services.yml](Resources/config/services.yml): see the service definitions for the above services.
- [exampleEmailLayout.txt.twig](Resources/views/exampleEmailLayout.txt.twig) & [exampleEmailLayout.html.twig](Resources/views/exampleEmailLayout.html.twig): Note: The txt.twig includes the html.twig at the bottom. 



## Send a single email programmatically
```php
$subject = "your email subject";
$recipientName = "John Doe";
$recipientEmail = "john@doe.com";
$locale = "en";

// compose the array of all things required to render your email with the selected template
$params['subject'] = $subject;
$params['name'] = $recipientName;
$params['age'] = 42;
$params['message'] = "Happy birthday I wish you all the best!!"

// get the AzineTwigSwiftMailer instance
$mailer = $this->getContainer()->get('azine_email_template_twig_swift_mailer');

// send the mail (depending on your configuration, the mail will be spooled, or sent directly)
$mailer->sendSingleEmail($recipientEmail, $recipientName, $subject, $params, EmailTemplateProvider::HAPPY_BIRTHDAY_MESSAGE . ".txt.twig", $locale);
```

## Send an email to one or more recipients with extra options
```php
// $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName all follow the same pattern.
// if $from is a string, then the name is added to the address => "John Doe <me@acme.com>"
// if $from is an array of strings, the name is ignored. => "me@acme.com, you@acme.com" 
$from = "me@acme.com";
$fromName = "John Doe";

// add attachments to the email either from in-memory data or from the filesystem
// the array-keys are used as filename in the email if they are longer than 4 characters else the files filename will be used.
// for generated data you must provide an array-key as filename.
$photo = $this->createAnInMemoryFileNotSavedToTheFileSystem();
$attachments = array('/some/location/nice.pdf', 'YourInvoice.pdf' => '/tmp/jqwetifjslkrj389w8t7lk3j.pdf', 'photo.jpg' => $photo);

$mailer = $this->getContainer()->get('azine_email_template_twig_swift_mailer');
$failedRecipients = array();
$mailer->sendEmail($failedRecipients, $subject, $from, $fromName, $to, $toName, $cc, $ccName, $bcc, $bccName, $replyTo, $replyToName, $params, AcmeTemplateProvider::SOME_FANCY_TEMPLATE, $attachments, "de", null);
for($failedRecipients as $failure){
    // do something with the email-address the caused a failure
}
```

## Create a simple text-notification email
```php
// get all elements used for the notification email
$title = "You have won the lottery!"
$content = "Congratulation John! You have won 7'000'000$ in the lottery";
$gotoUrl = "http://www.acmelottery.com/claim/you/money"; 
$recipientId = 33; // user id

// get your implementation of the AzineNotifierService
$notifierService = $this->get('your_notifier_service');
$notifierService->addNotificationMessage($recipientId, $title, $content, $goToUrl)
```

## Create a notification with a custom layout
```php
// get all elements used for the notification email
$title = "You have won the lottery!"
$content = "Congratulation John! You have won 7'000'000$ in the lottery";
$recipientId = 33; // user id
$templateVars['goToUrl'] = "http://www.acmelottery.com/claim/you/money";

// get template-vars for the custom layout
$templateProvider = $this->getContainer()->get();
$templateVars = $templateProvider->addTemplateVariablesFor(AcmeTemplateProvider::FANCY_TEMPLATE, $templateVars), Notification::IMPORTANCE_NORMAL, true);

// get your implementation of the AzineNotifierService
$notifierService = $this->get('your_notifier_service');
$notifierService->addNotification($recipientId, $title, $content, AcmeTemplateProvider::FANCY_TEMPLATE, $templateVars, Notification::IMPORTANCE_NORMAL, false);
```

## Automate sending emails and "housekeeping"
Of course you can run the send command from a cron-job or a terminal, but doing it 
with a scheduler (e.g. cron) is probably what you really want.

Here are the commands you will want to run periodically:
```php
// every minute
php app/console swiftmailer:spool:send                  // send all spooled mails (e.g. every minute)
php app/console  emails:sendNotifications               // Aggregate and send pending notifications via email.

// once every day or few days
php app/console  emails:clear-and-log-failures          // Clears and logs failed emails from the spool

// run this as required to free database space
php app/console  emails:remove-old-web-view-emails      // Remove all "SentEmail" from the database that are older than the configured time.
```

Here's an example cron-job line:
```php
// run job a 3 o'clock on the first day of the month and log output into the cron.log file
0 	3 	1 	* 	*  /usr/local/bin/php /home/acme/app/console emails:remove-old-web-view-emails -e prod >>/home/acme/app/logs/cron.log 2>&1 
```
