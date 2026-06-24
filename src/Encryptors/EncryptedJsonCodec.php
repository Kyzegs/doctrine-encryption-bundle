<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Encryptors;

use Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListenerInterface;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;

final readonly class EncryptedJsonCodec
{
    public const WRAPPER_KEY = '__doctrine_encrypted';
    public const VERSION = 1;

    public function __construct(private EncryptorInterface $encryptor)
    {
    }

    /** @return array<string, array{version: int, ciphertext: string}>|null */
    public function encrypt(mixed $value, string $columnName, string $context): ?array
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw new EncryptException(sprintf('Cannot encrypt JSON value at %s: expected array or null.', $context), $value);
        }

        if ($this->isEncryptedWrapper($value)) {
            return $value;
        }

        $this->assertSupportedArrayValues($value, $context);
        $ciphertext = $this->encryptor->encrypt($this->encodeJson($value, $context), $columnName);
        if (!is_string($ciphertext)) {
            throw new EncryptException(sprintf('Cannot encrypt JSON value at %s: encryptor returned null.', $context), $value);
        }

        return [
            self::WRAPPER_KEY => [
                'version' => self::VERSION,
                'ciphertext' => $ciphertext,
            ],
        ];
    }

    public function decrypt(mixed $value, string $columnName, string $context): mixed
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw new EncryptException(sprintf('Cannot decrypt JSON value at %s: expected array or null.', $context), $value);
        }

        if (!$this->isEncryptedWrapper($value)) {
            return $value;
        }

        /** @var array{version: int, ciphertext: string} $wrapper */
        $wrapper = $value[self::WRAPPER_KEY];
        $plaintext = $this->encryptor->decrypt($wrapper['ciphertext'], $columnName);
        if (!is_string($plaintext)) {
            throw new EncryptException(sprintf('Cannot decrypt JSON value at %s: encryptor returned null.', $context), $value);
        }

        $decoded = $this->decodeJson($plaintext, $context);
        if (!is_array($decoded)) {
            throw new EncryptException(sprintf('Cannot decrypt JSON value at %s: decrypted JSON must contain an array.', $context), $decoded);
        }

        return $decoded;
    }

    public function isEncryptedWrapper(mixed $value): bool
    {
        if (!is_array($value) || 1 !== count($value) || !array_key_exists(self::WRAPPER_KEY, $value)) {
            return false;
        }

        $wrapper = $value[self::WRAPPER_KEY];
        if (!is_array($wrapper) || 2 !== count($wrapper) || !array_key_exists('version', $wrapper) || !array_key_exists('ciphertext', $wrapper)) {
            return false;
        }

        return self::VERSION === $wrapper['version']
            && is_string($wrapper['ciphertext'])
            && str_ends_with($wrapper['ciphertext'], DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX);
    }

    public function encodeJson(mixed $value, string $context): string
    {
        try {
            return json_encode($value, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new EncryptException(sprintf('Cannot encode JSON value at %s: %s', $context, $exception->getMessage()), $value, 0, $exception);
        }
    }

    public function decodeJson(mixed $value, string $context): mixed
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new EncryptException(sprintf('Cannot decode JSON value at %s: expected database string or null.', $context), $value);
        }

        try {
            return json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new EncryptException(sprintf('Cannot decode JSON value at %s: %s', $context, $exception->getMessage()), $value, 0, $exception);
        }
    }

    /** @param array<mixed> $value */
    private function assertSupportedArrayValues(array $value, string $context): void
    {
        foreach ($value as $item) {
            if (is_object($item) || is_resource($item)) {
                throw new EncryptException(sprintf('Cannot encrypt JSON value at %s: arrays cannot contain objects or resources.', $context), $value);
            }

            if (is_array($item)) {
                $this->assertSupportedArrayValues($item, $context);
            }
        }
    }
}
