<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Services;

/**
 * @deprecated Use AzineTwigMailer. The implementation now uses Symfony Mailer.
 */
class AzineTwigSwiftMailer extends AzineTwigMailer implements TemplateTwigSwiftMailerInterface
{
}
