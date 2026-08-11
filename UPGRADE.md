Azine Email Bundle Upgrade Instructions
========================================

## Upgrade to PHP 8.5 / Symfony 7.4

This release is a new major-version upgrade. It keeps the existing Azine email feature set—multipart Twig templates, newsletters, notifications, account emails, inline images, attachments, tracking, test sends, spam scoring and persisted web views—while replacing unsupported framework integrations.

### Runtime requirements

- PHP `^8.5`;
- Symfony `^7.4`;
- Doctrine ORM `^3.6`;
- Twig `^3.14`;
- FOSUserBundle `^4.1` for registration and password-reset email integration;
- PHP extensions `ctype`, `fileinfo`, `filter`, `json` and `mailparse`;
- `gd` remains optional and is only needed when generated GD images are embedded.

### Swiftmailer to Symfony Mailer

Swiftmailer and `symfony/swiftmailer-bundle` are no longer used. Configure Symfony Mailer through `MAILER_DSN` and the normal Symfony FrameworkBundle mailer configuration.

The canonical application service is now:

```yaml
azine_email:
    template_twig_mailer: azine_email.default.template_twig_mailer
    immediate_mailer_service: mailer
```

The following legacy names remain as deprecated compatibility aliases for one migration cycle:

- `template_twig_swift_mailer`;
- `azine_email.default.template_twig_swift_mailer`;
- `TemplateTwigSwiftMailerInterface`;
- `AzineTwigSwiftMailer`.

New application code should use `TemplateTwigMailerInterface`, `AzineTwigMailer` and Symfony Mime's `Email` class.

### Asynchronous delivery and retries

The old serialized Swiftmailer file-spool command was removed. For asynchronous delivery or transport retries, route Symfony Mailer messages through Symfony Messenger and operate the Messenger worker using the application's normal process supervision.

The newsletter and notification commands continue to exist and use a filesystem lock to prevent overlapping executions:

```text
emails:sendNewsletter
emails:sendNotifications
emails:remove-old-web-view-emails
```

### Custom template providers

Existing providers extending `AzineTemplateProvider` remain source-compatible. To add headers to Symfony Mime messages, implement `SymfonyMailerTemplateProviderInterface` or extend `SymfonyMailerTemplateProvider`:

```php
public function addCustomHeadersToEmail(string $template, Email $message, array $params): void
{
    $message->getHeaders()->addTextHeader('X-Example', 'value');
}
```

### Custom notifier and web-view services

Application-specific subclasses of `AzineNotifierService`, `AzineTemplateProvider` and `AzineWebViewService` keep their historical extension-hook signatures. Their service definitions must inject current interfaces:

```yaml
arguments:
    $mailer: '@azine_email_template_twig_mailer'
    $managerRegistry: '@doctrine'
    $twig: '@twig'
```

Doctrine repositories must be requested using entity class names rather than bundle notation, and ORM 3 code must call `flush()` without an entity argument.

### Email templates

Modern Twig namespace notation is preferred:

```text
@AzineEmail/Email/newsletter.txt.twig
@App/Email/notifications.html.twig
```

The preview controller temporarily translates legacy `Bundle:Folder:template` notation so stored email records and existing application configuration can be migrated without losing web-view access.

### Spam scoring

The test-email spam-score feature now uses Symfony HttpClient and Postmark's HTTPS endpoint. Override only when necessary:

```yaml
azine_email:
    spam_check_endpoint: 'https://spamcheck.postmarkapp.com/filter'
```

Non-HTTPS endpoints are rejected.

### FOSUser and email-address confirmation

The mailer implements FOSUserBundle 4.1's `MailerInterface`. Registration and password-reset templates continue to use Twig blocks for `subject`, `body_text` and `body_html`.

When `azine/emailupdateconfirmation-bundle` is installed, configure its mailer to `azine_email.default.template_twig_mailer` to retain the same branded account-email rendering.

### Removed dependencies and duplicate code

- Swiftmailer and the custom SwiftmailerBundle;
- the obsolete Swiftmailer spool cleanup command;
- container-aware console commands;
- raw cURL spam-check code;
- duplicate Twig/Swiftmailer transport implementations.

### Deployment checks

Before deploying an application using this release:

1. configure `MAILER_DSN` and send representative registration, reset, notification and newsletter emails;
2. run the notification/newsletter commands and, when enabled, the Messenger worker;
3. verify HTML/text preview, inline images, attachments, spam scoring and stored web views;
4. run Doctrine schema validation and application migrations against a production-data copy;
5. run `composer audit --locked` on the final application lock file.

## Historical notes

Older 1.x–4.x releases used Swiftmailer, legacy Twig class names and Symfony bundle-notation repositories. Those APIs are retained only where explicitly documented above as short-lived source-compatibility aliases; they should not be used in new code.
