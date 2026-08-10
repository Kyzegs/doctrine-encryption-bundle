<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;

/**
 * Encrypts a string column at the DBAL layer instead of through entity lifecycle events.
 *
 * Conversion happens on the way to and from the driver, so this also covers the reads the listener never
 * sees: DQL scalar and array hydration, partial selects, and aggregate results.
 */
class EncryptedTextType extends Type
{
    public const NAME = 'encrypted_text';

    /**
     * Doctrine resolves types through a static registry rather than the container, and DoctrineBundle
     * replaces the registered instance when it initializes configured types, so an encryptor handed to one
     * instance does not survive. The bundle hands it to the class instead, during boot.
     *
     * ponytail: one encryptor per process. Two kernels booted together share it, last boot winning, which
     * only matters for a multi-tenant setup running distinct keys in one process.
     */
    private static ?EncryptorInterface $encryptor = null;

    public static function setEncryptor(?EncryptorInterface $encryptor): void
    {
        self::$encryptor = $encryptor;
    }

    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $value = $this->toStringOrNull($value, 'encrypt');

        return null === $value ? null : $this->encryptor()->encrypt($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $value = $this->toStringOrNull($value, 'decrypt');

        return null === $value ? null : $this->encryptor()->decrypt($value);
    }

    /** Required by DBAL 3, and harmless on DBAL 4, which resolves types through the registry alone. */
    public function getName(): string
    {
        return self::NAME;
    }

    private function toStringOrNull(mixed $value, string $operation): ?string
    {
        if (null === $value) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        throw new EncryptException(sprintf('The "%s" type cannot %s a value of type %s.', self::NAME, $operation, get_debug_type($value)));
    }

    private function encryptor(): EncryptorInterface
    {
        if (!self::$encryptor instanceof EncryptorInterface) {
            throw new EncryptException(sprintf('The "%s" type has no encryptor. It is available once DoctrineEncryptionBundle has booted.', self::NAME));
        }

        return self::$encryptor;
    }
}
