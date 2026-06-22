<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Key;

use SpecShaper\EncryptBundle\Exception\EncryptException;

final class StaticKeyProvider implements KeyProviderInterface
{
    /** @var array<string, string> */
    private array $keys;

    /**
     * @param array<array-key, string> $decryptionKeys Base64-encoded keys indexed by key ID
     */
    public function __construct(
        string $currentKey,
        private readonly string $currentKeyId = 'default',
        array $decryptionKeys = [],
    ) {
        $this->assertKeyId($currentKeyId);

        $keys = $decryptionKeys;
        $keys[$currentKeyId] = $currentKey;

        foreach ($keys as $keyId => $key) {
            $this->assertKeyId((string) $keyId);
            $this->keys[(string) $keyId] = $this->decodeKey($key, (string) $keyId);
        }
    }

    public function currentKeyId(): string
    {
        return $this->currentKeyId;
    }

    public function currentKey(): string
    {
        return $this->keys[$this->currentKeyId];
    }

    public function key(string $keyId): string
    {
        if (!isset($this->keys[$keyId])) {
            throw new EncryptException(sprintf('No encryption key is configured for key ID "%s".', $keyId));
        }

        return $this->keys[$keyId];
    }

    private function assertKeyId(string $keyId): void
    {
        if (1 !== preg_match('/^[A-Za-z0-9._-]{1,64}$/', $keyId)) {
            throw new EncryptException('Encryption key IDs must contain only letters, numbers, dot, underscore, or hyphen.');
        }
    }

    private function decodeKey(string $encodedKey, string $keyId): string
    {
        $key = base64_decode($encodedKey, true);

        if (false === $key || 32 !== strlen($key)) {
            throw new EncryptException(sprintf('Encryption key "%s" must be a strictly base64-encoded 256-bit key.', $keyId));
        }

        return $key;
    }
}
