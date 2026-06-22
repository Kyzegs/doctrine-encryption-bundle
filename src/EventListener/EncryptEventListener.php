<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\EventListener;

use SpecShaper\EncryptBundle\Encryptors\EncryptorInterface;
use SpecShaper\EncryptBundle\Event\EncryptEventInterface;

/**
 * Doctrine event listener which encrypts/decrypts entities.
 */
final readonly class EncryptEventListener
{
    /**
     * Encryptor created by the factory service.
     */
    public function __construct(
        private EncryptorInterface $encryptor,
        private bool $isDisabled,
    ) {
    }

    /**
     * Return the encryptor.
     */
    public function getEncryptor(): EncryptorInterface
    {
        return $this->encryptor;
    }

    /**
     * Use an Encrypt even to encrypt a value.
     */
    public function encrypt(EncryptEventInterface $event): EncryptEventInterface
    {
        $value = $event->getValue();

        if (false === $this->isDisabled) {
            $value = $this->encryptor->encrypt($value);
        }

        $event->setValue($value);

        return $event;
    }

    /**
     * Use a decrypt event to decrypt a single value.
     */
    public function decrypt(EncryptEventInterface $event): EncryptEventInterface
    {
        $value = $event->getValue();

        $decrypted = $this->getEncryptor()->decrypt($value);

        $event->setValue($decrypted);

        return $event;
    }

    /**
     * Decrypt a value.
     *
     * If the value is an object, or if it does not contain the suffic <ENC> then return the value iteslf back.
     * Otherwise, decrypt the value and return.
     */
    public function decryptValue(?string $value): ?string
    {
        return $this->getEncryptor()->decrypt($value);
    }
}
