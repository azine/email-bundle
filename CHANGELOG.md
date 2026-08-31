# Changelog

## 5.0.1

- Extend `AzineTwigMailer::sendEmailUpdateConfirmationMessage()` with an optional explicit recipient address.
- Preserve the existing two-argument call as a backwards-compatible fallback to `UserInterface::getEmail()`.
- Allow the Email Update Confirmation bundle to send the confirmation link to the pending new address after the Doctrine listener restores the currently persisted address.
- Add regression coverage proving that the explicit new address is used.

## 5.0.0

- Upgrade to PHP 8.5, Symfony 7.4, Doctrine ORM 3.6, FOSUserBundle 4.1, Twig 3 and PHPUnit 12.
- Replace Swiftmailer delivery with Symfony Mailer and Symfony Mime while retaining the established application integration contracts.
- Add stable and lowest dependency CI with strict PHP linting, deprecation diagnostics and PHPUnit notice gating.
- Modernize commands, controllers, configuration, dependency injection, Doctrine persistence integration, templates and documentation.
