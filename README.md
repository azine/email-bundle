# Azine Email Bundle

Symfony bundle for multipart Twig emails, newsletters, notifications, account emails, browser previews, persisted web views, inline images, attachments and campaign/open tracking.

## Requirements

- PHP 8.5
- Symfony 7.4
- Doctrine ORM 3.6
- Twig 3.14
- FOSUserBundle 4.1
- PHP extensions `ctype`, `fileinfo`, `filter`, `json` and `mailparse`
- Optional `gd` when generated GD images are embedded

Version 5 replaces Swiftmailer with Symfony Mailer. It does not require `swiftmailer/swiftmailer` or `symfony/swiftmailer-bundle`.

## Installation

```bash
composer require azine/email-bundle:^5.0
```

Register the bundle in `config/bundles.php`:

```php
<?php

return [
    // ...
    Azine\EmailBundle\AzineEmailBundle::class => ['all' => true],
];
```

Import the routes when the preview and web-view controllers are used:

```yaml
# config/routes/azine_email.yaml
azine_email:
    resource: '@AzineEmailBundle/Resources/config/routing.yml'
```

Configure Symfony Mailer in the application, normally through `MAILER_DSN`:

```dotenv
MAILER_DSN=smtp://user:password@example.test:587
```

Tests and non-production environments should use a non-delivering transport such as `null://null` or Symfony's in-memory test transport.

## Minimal configuration

```yaml
# config/packages/azine_email.yaml
azine_email:
    recipient_class: App\Entity\User
    recipient_newsletter_field: newsletter
    notifier_service: App\Email\NotifierService
    template_provider: App\Email\TemplateProvider
    recipient_provider: azine_email.default.recipient_provider
    template_twig_mailer: azine_email.default.template_twig_mailer
    immediate_mailer_service: mailer

    no_reply:
        email: no-reply@example.test
        name: Example application

    image_dir: '%kernel.project_dir%/assets/email/'
    allowed_images_folders:
        - '%kernel.project_dir%/assets/email/'

    newsletter:
        interval: 14
        send_time: '10:00'

    templates:
        newsletter: '@App/Email/newsletter'
        notifications: '@App/Email/notifications'
        content_item: '@App/Email/content_item/message'

    web_view_retention: 90
    web_view_service: App\Email\WebViewService
```

`template_twig_mailer` is the canonical configuration key. The old `template_twig_swift_mailer` key and Swiftmailer-named service/class aliases exist only as a temporary migration bridge and must not be used by new code.

## Sending a Twig email

Inject `Azine\EmailBundle\Services\TemplateTwigMailerInterface` or the `azine_email.default.template_twig_mailer` service:

```php
<?php

use Azine\EmailBundle\Services\TemplateTwigMailerInterface;

final class WelcomeMailer
{
    public function __construct(private readonly TemplateTwigMailerInterface $mailer)
    {
    }

    public function send(string $recipient, string $name): bool
    {
        $message = null;

        return $this->mailer->sendSingleEmail(
            $recipient,
            $name,
            'Welcome',
            ['name' => $name],
            '@App/Email/welcome.txt.twig',
            'en',
            message: $message,
        );
    }
}
```

The wrapper template must provide the `subject`, `body_text` and `body_html` blocks when it is used for FOSUser/account-email integration. Application templates may supply the subject explicitly when calling the mailer.

## Template providers

Extend `AzineTemplateProvider` to add application-specific variables, snippets, images, web-view behavior and campaign parameters. For Symfony Mime headers, implement `SymfonyMailerTemplateProviderInterface` or extend `SymfonyMailerTemplateProvider`:

```php
<?php

use Symfony\Component\Mime\Email;

public function addCustomHeadersToEmail(string $template, Email $message, array $params): void
{
    $message->getHeaders()->addTextHeader('X-Application', 'example');
}
```

Only files below `allowed_images_folders` are embedded. Referenced images are attached with content IDs; web-view variables are converted through the configured template provider before persistence.

## Notifications and newsletters

The supported commands are:

```text
emails:sendNewsletter
emails:sendNotifications
emails:remove-old-web-view-emails
```

Newsletter and notification commands use Symfony Lock to avoid overlapping runs. Symfony Mailer/Messenger should be used for asynchronous transport delivery and retries; the old serialized Swiftmailer spool cleanup command has been removed.

## Web preview and persisted web views

The bundle can:

- render configured email templates in a browser;
- send a test message through Symfony Mailer;
- request a spam score through the configured HTTPS endpoint;
- persist selected sent-message variables and serve a browser web view;
- remove expired `SentEmail` rows with `emails:remove-old-web-view-emails`.

Run the application's reviewed Doctrine migrations for `Notification` and `SentEmail`; do not use `doctrine:schema:update --force` as a deployment strategy.

## Campaign and open tracking

The default campaign keys are `utm_campaign`, `utm_term`, `utm_content`, `utm_medium` and `utm_source`. Configure `domains_for_tracking` to restrict links that may receive tracking parameters. Open tracking is optional and uses the configured `email_open_tracking_code_builder`.

## FOSUserBundle and email-change confirmation

The mailer implements FOSUserBundle 4.1's mailer contract for registration and password-reset messages. When `azine/emailupdateconfirmation-bundle` is installed, configure it to use:

```yaml
azine_email_update_confirmation:
    mailer: azine_email.default.template_twig_mailer
```

## Development

```bash
composer validate --strict --no-check-publish
composer update
find Command Controller DependencyInjection Entity Form Services Tests -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpunit -c phpunit.xml.dist
```

GitHub Actions runs the full PHPUnit 12 suite on PHP 8.5 with stable and lowest supported dependency sets. `MAILER_DSN=null://null` prevents real delivery in CI.

See [UPGRADE.md](UPGRADE.md) for the 4.x to 5.0 migration and application verification checklist.
