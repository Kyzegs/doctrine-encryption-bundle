<?php

use SpecShaper\EncryptBundle\Annotations\BlindIndex;
use SpecShaper\EncryptBundle\Annotations\Encrypted;
use SpecShaper\EncryptBundle\Encryptors\AesGcmEncryptor;
use SpecShaper\EncryptBundle\Encryptors\EncryptorInterface;
use SpecShaper\EncryptBundle\Event\EncryptEvent;
use SpecShaper\EncryptBundle\EventListener\DoctrineEncryptListener;
use SpecShaper\EncryptBundle\Exception\EncryptException;
use SpecShaper\EncryptBundle\SpecShaperEncryptBundle;

final class Customer
{
    #[Encrypted]
    public string $secret;

    #[BlindIndex(sourceField: 'secret')]
    public ?string $secretLookup = null;

    public function __construct(
        private readonly EncryptorInterface $encryptor,
    ) {
    }

    public function encrypt(string $value): ?string
    {
        try {
            return $this->encryptor->encrypt($value);
        } catch (EncryptException) {
            return null;
        }
    }
}

$classes = [
    SpecShaperEncryptBundle::class,
    DoctrineEncryptListener::class,
    AesGcmEncryptor::class,
    EncryptEvent::class,
];

-----
<?php

use Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted;
use Kyzegs\DoctrineEncryptionBundle\Attribute\BlindIndex;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\DoctrineEncryptionBundle;
use Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListener;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\AesGcmEncryptor;
use Kyzegs\DoctrineEncryptionBundle\Event\EncryptEvent;

final class Customer
{
    #[Encrypted]
    public string $secret;

    #[BlindIndex(sourceField: 'secret')]
    public ?string $secretLookup = null;

    public function __construct(
        private readonly EncryptorInterface $encryptor,
    ) {
    }

    public function encrypt(string $value): ?string
    {
        try {
            return $this->encryptor->encrypt($value);
        } catch (EncryptException) {
            return null;
        }
    }
}

$classes = [
    DoctrineEncryptionBundle::class,
    DoctrineEncryptListener::class,
    AesGcmEncryptor::class,
    EncryptEvent::class,
];
