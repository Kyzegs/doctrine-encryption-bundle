<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Encryptors;

use SpecShaper\EncryptBundle\Exception\EncryptException;
use SpecShaper\EncryptBundle\Key\KeyProviderInterface;
use SpecShaper\EncryptBundle\Key\StaticKeyProvider;
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
     */
    public function createService(?string $encryptKey = null, ?string $defaultAssociatedData = null, ?string $encryptorClass = self::SUPPORTED_EXTENSION_OPENSSL): EncryptorInterface
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

        return $encryptor;
    }
}
