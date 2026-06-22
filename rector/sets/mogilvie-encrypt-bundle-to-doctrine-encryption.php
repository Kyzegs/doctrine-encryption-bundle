<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

/*
 * Migrates PHP code from mogilvie/EncryptBundle (specshaper/encrypt-bundle)
 * to kyzegs/doctrine-encryption-bundle.
 *
 * Usage from an application's rector.php:
 *
 *     ->withSets([DoctrineEncryptionSetList::MIGRATE_FROM_SPEC_SHAPER_ENCRYPT_BUNDLE])
 */
return RectorConfig::configure()
    ->withImportNames(removeUnusedImports: true)
    ->withConfiguredRule(RenameClassRector::class, [
        'SpecShaper\EncryptBundle\Annotations\BlindIndex' => Kyzegs\DoctrineEncryptionBundle\Attribute\BlindIndex::class,
        'SpecShaper\EncryptBundle\Annotations\Encrypted' => Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted::class,
        'SpecShaper\EncryptBundle\Attribute\BlindIndex' => Kyzegs\DoctrineEncryptionBundle\Attribute\BlindIndex::class,
        'SpecShaper\EncryptBundle\Attribute\Encrypted' => Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted::class,
        'SpecShaper\EncryptBundle\BlindIndex\BlindIndexField' => Kyzegs\DoctrineEncryptionBundle\BlindIndex\BlindIndexField::class,
        'SpecShaper\EncryptBundle\BlindIndex\BlindIndexMetadataProvider' => Kyzegs\DoctrineEncryptionBundle\BlindIndex\BlindIndexMetadataProvider::class,
        'SpecShaper\EncryptBundle\BlindIndex\BlindIndexUpdater' => Kyzegs\DoctrineEncryptionBundle\BlindIndex\BlindIndexUpdater::class,
        'SpecShaper\EncryptBundle\Command\BlindIndexDatabaseCommand' => Kyzegs\DoctrineEncryptionBundle\Command\BlindIndexDatabaseCommand::class,
        'SpecShaper\EncryptBundle\Command\EncryptDatabaseCommand' => Kyzegs\DoctrineEncryptionBundle\Command\EncryptDatabaseCommand::class,
        'SpecShaper\EncryptBundle\Command\GenKeyCommand' => Kyzegs\DoctrineEncryptionBundle\Command\GenKeyCommand::class,
        'SpecShaper\EncryptBundle\DependencyInjection\Configuration' => Kyzegs\DoctrineEncryptionBundle\DependencyInjection\Configuration::class,
        'SpecShaper\EncryptBundle\DependencyInjection\SpecShaperEncryptExtension' => Kyzegs\DoctrineEncryptionBundle\DependencyInjection\DoctrineEncryptionExtension::class,
        'SpecShaper\EncryptBundle\Encryptors\AesCbcEncryptor' => Kyzegs\DoctrineEncryptionBundle\Encryptors\AesCbcEncryptor::class,
        'SpecShaper\EncryptBundle\Encryptors\AesGcmEncryptor' => Kyzegs\DoctrineEncryptionBundle\Encryptors\AesGcmEncryptor::class,
        'SpecShaper\EncryptBundle\Encryptors\CiphertextEnvelope' => Kyzegs\DoctrineEncryptionBundle\Encryptors\CiphertextEnvelope::class,
        'SpecShaper\EncryptBundle\Encryptors\EncryptorFactory' => Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorFactory::class,
        'SpecShaper\EncryptBundle\Encryptors\EncryptorInterface' => Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface::class,
        'SpecShaper\EncryptBundle\Encryptors\KeyProviderAwareInterface' => Kyzegs\DoctrineEncryptionBundle\Encryptors\KeyProviderAwareInterface::class,
        'SpecShaper\EncryptBundle\Event\DecryptEvent' => Kyzegs\DoctrineEncryptionBundle\Event\DecryptEvent::class,
        'SpecShaper\EncryptBundle\Event\EncryptEvent' => Kyzegs\DoctrineEncryptionBundle\Event\EncryptEvent::class,
        'SpecShaper\EncryptBundle\Event\EncryptEventInterface' => Kyzegs\DoctrineEncryptionBundle\Event\EncryptEventInterface::class,
        'SpecShaper\EncryptBundle\Event\EncryptEvents' => Kyzegs\DoctrineEncryptionBundle\Event\EncryptEvents::class,
        'SpecShaper\EncryptBundle\Event\EncryptKeyEvent' => Kyzegs\DoctrineEncryptionBundle\Event\EncryptKeyEvent::class,
        'SpecShaper\EncryptBundle\Event\EncryptKeyEvents' => Kyzegs\DoctrineEncryptionBundle\Event\EncryptKeyEvents::class,
        'SpecShaper\EncryptBundle\EventListener\DoctrineEncryptListener' => Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListener::class,
        'SpecShaper\EncryptBundle\EventListener\DoctrineEncryptListenerInterface' => Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListenerInterface::class,
        'SpecShaper\EncryptBundle\EventListener\EncryptEventListener' => Kyzegs\DoctrineEncryptionBundle\EventListener\EncryptEventListener::class,
        'SpecShaper\EncryptBundle\Exception\EncryptException' => Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException::class,
        'SpecShaper\EncryptBundle\Hashers\BlindIndexHasherInterface' => Kyzegs\DoctrineEncryptionBundle\Hashers\BlindIndexHasherInterface::class,
        'SpecShaper\EncryptBundle\Hashers\HmacBlindIndexHasher' => Kyzegs\DoctrineEncryptionBundle\Hashers\HmacBlindIndexHasher::class,
        'SpecShaper\EncryptBundle\Key\KeyProviderInterface' => Kyzegs\DoctrineEncryptionBundle\Key\KeyProviderInterface::class,
        'SpecShaper\EncryptBundle\Key\StaticKeyProvider' => Kyzegs\DoctrineEncryptionBundle\Key\StaticKeyProvider::class,
        'SpecShaper\EncryptBundle\Mapping\EncryptedField' => Kyzegs\DoctrineEncryptionBundle\Mapping\EncryptedField::class,
        'SpecShaper\EncryptBundle\Mapping\EncryptedFieldMetadataProvider' => Kyzegs\DoctrineEncryptionBundle\Mapping\EncryptedFieldMetadataProvider::class,
        'SpecShaper\EncryptBundle\SpecShaperEncryptBundle' => Kyzegs\DoctrineEncryptionBundle\DoctrineEncryptionBundle::class,
        'SpecShaper\EncryptBundle\Twig\EncryptExtension' => Kyzegs\DoctrineEncryptionBundle\Twig\EncryptExtension::class,
    ]);
