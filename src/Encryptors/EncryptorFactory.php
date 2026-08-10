<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Encryptors;

use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Key\KeyProviderInterface;
use Kyzegs\DoctrineEncryptionBundle\Key\StaticKeyProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class EncryptorFactory
{
    public const SUPPORTED_EXTENSION_OPENSSL = AesGcmEncryptor::class;

    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ?KeyProviderInterface $keyProvider = null,
    ) {
    }

    /**
     * Create service will return the desired encryption service.
     *
     * @param string      $encryptKey            256-bit encryption key
     * @param string      $defaultAssociatedData a fallback string used for AES-GBC-256 encryption
     * @param string|null $encryptorClass        the desired encryptor, defaults to OpenSSL, but can be overridden by passing a classname
     * @param bool        $allowLegacyCbc        whether unauthenticated AES-CBC ciphertext may still be read
     * @param bool        $verifyAssociatedData  whether a ciphertext must be read from the column it was written to
     */
    public function createService(?string $encryptKey = null, ?string $defaultAssociatedData = null, ?string $encryptorClass = self::SUPPORTED_EXTENSION_OPENSSL, bool $allowLegacyCbc = false, bool $verifyAssociatedData = true): EncryptorInterface
    {
        $encryptor = new $encryptorClass($this->dispatcher);
        if (!$encryptor instanceof EncryptorInterface) {
            throw new EncryptException(sprintf('Configured encryptor "%s" must implement %s.', $encryptorClass, EncryptorInterface::class));
        }

        $keyProvider = $this->keyProvider;
        if (!$keyProvider instanceof KeyProviderInterface && null !== $encryptKey) {
            $keyProvider = new StaticKeyProvider($encryptKey);
        }

        if ($encryptor instanceof KeyProviderAwareInterface && $keyProvider instanceof KeyProviderInterface) {
            $encryptor->setKeyProvider($keyProvider);
        } elseif (null !== $encryptKey) {
            $encryptor->setSecretKey($encryptKey);
        } else {
            throw new EncryptException(sprintf('Configured encryptor "%s" does not support key providers.', $encryptorClass));
        }

        if (method_exists($encryptor, 'setDefaultAssociatedData')) {
            $encryptor->setDefaultAssociatedData($defaultAssociatedData);
        }

        if (method_exists($encryptor, 'setLegacyCbcAllowed')) {
            $encryptor->setLegacyCbcAllowed($allowLegacyCbc);
        }

        if (method_exists($encryptor, 'setAssociatedDataVerified')) {
            $encryptor->setAssociatedDataVerified($verifyAssociatedData);
        }

        return $encryptor;
    }
}
