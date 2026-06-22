<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Encryptors;

use SpecShaper\EncryptBundle\Event\EncryptKeyEvent;
use SpecShaper\EncryptBundle\Event\EncryptKeyEvents;
use SpecShaper\EncryptBundle\EventListener\DoctrineEncryptListenerInterface;
use SpecShaper\EncryptBundle\Exception\EncryptException;
use SpecShaper\EncryptBundle\Key\KeyProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** @deprecated Prefer AesGcmEncryptor. CBC is retained for compatibility only. */
class AesCbcEncryptor implements EncryptorInterface, KeyProviderAwareInterface, \Stringable
{
    public const METHOD = 'aes-256-cbc';

    private ?string $secretKey = null;
    private ?KeyProviderInterface $keyProvider = null;

    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function __toString(): string
    {
        return self::class.':'.self::METHOD;
    }

    /** @deprecated Inject a KeyProviderInterface and call setKeyProvider() instead. */
    public function setSecretKey(string $secretKey): void
    {
        $this->secretKey = $secretKey;
    }

    public function setKeyProvider(KeyProviderInterface $keyProvider): void
    {
        $this->keyProvider = $keyProvider;
    }

    public function encrypt(?string $data, ?string $columnName = null): ?string
    {
        if (null === $data) {
            return null;
        }

        if (str_ends_with($data, DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX)) {
            return $data;
        }

        $keyId = $this->keyProvider?->currentKeyId() ?? 'default';
        $key = $this->validateKey($this->keyProvider?->currentKey() ?? $this->key($keyId));
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if ($ivLength < 1) {
            throw new EncryptException('OpenSSL does not support AES-256-CBC.');
        }
        $iv = random_bytes($ivLength);
        $ciphertext = openssl_encrypt($data, self::METHOD, $key, \OPENSSL_RAW_DATA, $iv);

        if (false === $ciphertext) {
            throw new EncryptException('AES-CBC encryption failed.');
        }

        return CiphertextEnvelope::encode('cbc', $keyId, '', $iv.$ciphertext);
    }

    public function decrypt(?string $data, ?string $columnName = null): ?string
    {
        if (null === $data || !str_ends_with($data, DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX)) {
            return $data;
        }

        if (DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX === $data) {
            return '';
        }

        $envelope = CiphertextEnvelope::decode($data);
        if (null !== $envelope) {
            if ('cbc' !== $envelope['algorithm']) {
                throw new EncryptException(sprintf('The CBC compatibility encryptor cannot decrypt "%s" ciphertext.', $envelope['algorithm']));
            }
            $payload = $envelope['payload'];
            $key = $this->key($envelope['key_id']);
        } else {
            $payload = base64_decode(substr($data, 0, -strlen(DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX)), true);
            if (false === $payload) {
                throw new EncryptException('The legacy encrypted value is not valid base64.');
            }
            $key = $this->key($this->keyProvider?->currentKeyId() ?? 'default');
        }

        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if (strlen($payload) < $ivLength + 1) {
            throw new EncryptException('The AES-CBC ciphertext is truncated.');
        }

        $plaintext = openssl_decrypt(substr($payload, $ivLength), self::METHOD, $key, \OPENSSL_RAW_DATA, substr($payload, 0, $ivLength));
        if (false === $plaintext) {
            throw new EncryptException('AES-CBC decryption failed.');
        }

        return $plaintext;
    }

    private function key(string $keyId): string
    {
        if ($this->keyProvider instanceof KeyProviderInterface) {
            return $this->validateKey($this->keyProvider->key($keyId));
        }

        if ('default' !== $keyId) {
            throw new EncryptException(sprintf('No legacy encryption key is available for key ID "%s".', $keyId));
        }

        $event = new EncryptKeyEvent();
        $this->dispatcher->dispatch($event, EncryptKeyEvents::LOAD_KEY);
        $encodedKey = $event->getKey() ?? $this->secretKey;
        $key = null === $encodedKey ? false : base64_decode($encodedKey, true);

        if (false === $key || 32 !== strlen($key)) {
            throw new EncryptException('The bundle requires a strictly base64-encoded 256-bit encryption key.');
        }

        return $key;
    }

    private function validateKey(string $key): string
    {
        if (32 !== strlen($key)) {
            throw new EncryptException('Key providers must return a 256-bit binary encryption key.');
        }

        return $key;
    }
}
