<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Encryptors;

use Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListenerInterface;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;

final class CiphertextEnvelope
{
    private const PREFIX = 'SSEB1';

    /** Shortest legacy payload: a 12-byte GCM IV plus a 16-byte tag. */
    private const MINIMUM_LEGACY_PAYLOAD_LENGTH = 28;

    /**
     * Reports whether a value is already ciphertext rather than plaintext that merely ends with the suffix.
     *
     * ponytail: unversioned legacy values carry no marker, so they are recognised by strict base64 of at least
     * one IV plus tag. Rotating legacy rows onto the versioned envelope removes the heuristic entirely.
     */
    public static function looksLikeCiphertext(string $value): bool
    {
        if (!str_ends_with($value, DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX)) {
            return false;
        }

        $encoded = substr($value, 0, -strlen(DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX));

        if (str_starts_with($encoded, self::PREFIX.':')) {
            return true;
        }

        $payload = base64_decode($encoded, true);

        return false !== $payload && strlen($payload) >= self::MINIMUM_LEGACY_PAYLOAD_LENGTH;
    }

    public static function encode(string $algorithm, string $keyId, string $associatedData, string $payload): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9._-]{1,64}$/', $keyId)) {
            throw new EncryptException('Ciphertext key IDs must contain only letters, numbers, dot, underscore, or hyphen.');
        }

        return implode(':', [
            self::PREFIX,
            $algorithm,
            $keyId,
            self::base64UrlEncode($associatedData),
            self::base64UrlEncode($payload),
        ]).DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX;
    }

    /** @return array{algorithm: string, key_id: string, associated_data: string, payload: string}|null */
    public static function decode(string $ciphertext): ?array
    {
        return self::decodeValue($ciphertext)?->toArray();
    }

    public static function decodeValue(string $ciphertext): ?DecodedCiphertextEnvelope
    {
        if (!str_ends_with($ciphertext, DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX)) {
            return null;
        }

        $encoded = substr($ciphertext, 0, -strlen(DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX));

        if (!str_starts_with($encoded, self::PREFIX.':')) {
            return null;
        }

        $parts = explode(':', $encoded);
        if (5 !== count($parts) || '' === $parts[1] || '' === $parts[2]) {
            throw new EncryptException('The encrypted value has an invalid ciphertext envelope.');
        }
        if (1 !== preg_match('/^[A-Za-z0-9._-]{1,64}$/', $parts[2])) {
            throw new EncryptException('The encrypted value contains an invalid key ID.');
        }

        return new DecodedCiphertextEnvelope(
            algorithm: $parts[1],
            keyId: $parts[2],
            associatedData: self::base64UrlDecode($parts[3]),
            payload: self::base64UrlDecode($parts[4]),
        );
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]*$/', $value)) {
            throw new EncryptException('The encrypted value contains invalid base64url data.');
        }

        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', $padding), true);

        if (false === $decoded) {
            throw new EncryptException('The encrypted value contains invalid base64url data.');
        }

        return $decoded;
    }
}
