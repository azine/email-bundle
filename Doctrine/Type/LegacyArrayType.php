<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Doctrine\Type;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDbalType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

#[AsDbalType(name: self::NAME)]
final class LegacyArrayType extends Type
{
    public const NAME = 'azine_email_legacy_array';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('The %s Doctrine type expects an array or null, got %s.', self::NAME, get_debug_type($value)));
        }

        return serialize($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('The %s Doctrine type expects a serialized string from the database, got %s.', self::NAME, get_debug_type($value)));
        }

        $result = unserialize($value, ['allowed_classes' => [\DateTime::class, \DateTimeImmutable::class]]);
        if (!is_array($result)) {
            throw new \UnexpectedValueException(sprintf('The database value for Doctrine type %s is not a serialized array.', self::NAME));
        }

        return $result;
    }
}
