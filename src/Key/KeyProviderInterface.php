<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Key;

interface KeyProviderInterface
{
    public function currentKeyId(): string;

    /** Returns the current 32-byte binary encryption key. */
    public function currentKey(): string;

    /** Returns a 32-byte binary key by ID, including retired decryption keys. */
    public function key(string $keyId): string;
}
