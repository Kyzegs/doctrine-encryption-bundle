<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Encryptors;

use Kyzegs\DoctrineEncryptionBundle\Event\EncryptKeyEvent;
use Kyzegs\DoctrineEncryptionBundle\Event\EncryptKeyEvents;
use Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListenerInterface;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Key\KeyProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AesGcmEncryptor implements EncryptorInterface, KeyProviderAwareInterface, \Stringable
{
    public const METHOD = 'aes-256-gcm';
    private const ENVELOPE_ALGORITHM = 'gcm';
    private const TAG_LENGTH = 16;

    private ?string $secretKey = null;
    private ?KeyProviderInterface $keyProvider = null;
    private ?string $defaultAssociatedData = null;

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

    public function setDefaultAssociatedData(?string $defaultAssociatedData): void
    {
        $this->defaultAssociatedData = $defaultAssociatedData;
    }

    public function encrypt(?string $data, ?string $columnName = null): ?string
    {
        if (null === $data) {
            return null;
        }

        if (str_ends_with($data, DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX)) {
            return $data;
        }

        $keyId = $this->currentKeyId();
        $key = $this->validateKey($this->keyProvider?->currentKey() ?? $this->key($keyId));
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if ($ivLength < 1) {
            throw new EncryptException('OpenSSL does not support AES-256-GCM.');
        }
        $iv = random_bytes($ivLength);
        $tag = '';
        $associatedData = $columnName ?? $this->defaultAssociatedData ?? '';
        $ciphertext = openssl_encrypt($data, self::METHOD, $key, \OPENSSL_RAW_DATA, $iv, $tag, $associatedData, self::TAG_LENGTH);

        if (false === $ciphertext || self::TAG_LENGTH !== strlen($tag)) {
            throw new EncryptException('AES-GCM encryption failed.');
        }

        return CiphertextEnvelope::encode(self::ENVELOPE_ALGORITHM, $keyId, $associatedData, $iv.$tag.$ciphertext);
    }

    public function decrypt(?string $data, ?string $columnName = null): ?string
    {
        if (null === $data || !str_ends_with($data, DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX)) {
            return $data;
        }

        if (DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX === $data) {
            return '';
        }

        $envelope = CiphertextEnvelope::decodeValue($data);
        if ($envelope instanceof DecodedCiphertextEnvelope) {
            return $this->decryptEnvelope($envelope);
        }

        return $this->decryptLegacy($data, $columnName);
    }

    private function decryptEnvelope(DecodedCiphertextEnvelope $envelope): string
    {
        $key = $this->key($envelope->keyId);

        return match ($envelope->algorithm) {
            self::ENVELOPE_ALGORITHM => $this->decryptGcmPayload($envelope->payload, $key, $envelope->associatedData),
            'cbc' => $this->decryptCbcPayload($envelope->payload, $key),
            default => throw new EncryptException(sprintf('Unsupported ciphertext algorithm "%s".', $envelope->algorithm)),
        };
    }

    private function decryptLegacy(string $data, ?string $columnName): string
    {
        $encoded = substr($data, 0, -strlen(DoctrineEncryptListenerInterface::ENCRYPTED_SUFFIX));
        $payload = base64_decode($encoded, true);

        if (false === $payload) {
            throw new EncryptException('The legacy encrypted value is not valid base64.');
        }

        $key = $this->key($this->currentKeyId());
        $associatedData = $columnName ?? $this->defaultAssociatedData ?? '';

        try {
            return $this->decryptGcmPayload($payload, $key, $associatedData);
        } catch (EncryptException) {
            // The historical default was CBC and legacy values carry no algorithm marker.
            return $this->decryptCbcPayload($payload, $key);
        }
    }

    private function decryptGcmPayload(string $payload, string $key, string $associatedData): string
    {
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if (strlen($payload) < $ivLength + self::TAG_LENGTH + 1) {
            throw new EncryptException('The AES-GCM ciphertext is truncated.');
        }

        $iv = substr($payload, 0, $ivLength);
        $tag = substr($payload, $ivLength, self::TAG_LENGTH);
        $ciphertext = substr($payload, $ivLength + self::TAG_LENGTH);
        $plaintext = openssl_decrypt($ciphertext, self::METHOD, $key, \OPENSSL_RAW_DATA, $iv, $tag, $associatedData);

        if (false === $plaintext) {
            throw new EncryptException('AES-GCM authentication or decryption failed.');
        }

        return $plaintext;
    }

    private function decryptCbcPayload(string $payload, string $key): string
    {
        $ivLength = openssl_cipher_iv_length(AesCbcEncryptor::METHOD);
        if (strlen($payload) < $ivLength + 1) {
            throw new EncryptException('The AES-CBC ciphertext is truncated.');
        }

        $plaintext = openssl_decrypt(
            substr($payload, $ivLength),
            AesCbcEncryptor::METHOD,
            $key,
            \OPENSSL_RAW_DATA,
            substr($payload, 0, $ivLength),
        );

        if (false === $plaintext) {
            throw new EncryptException('Legacy AES-CBC decryption failed.');
        }

        return $plaintext;
    }

    private function currentKeyId(): string
    {
        return $this->keyProvider?->currentKeyId() ?? 'default';
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
