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
        'SpecShaper\EncryptBundle\Annotations\Encrypted' => Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted::class,
        'SpecShaper\EncryptBundle\Command\EncryptDatabaseCommand' => Kyzegs\DoctrineEncryptionBundle\Command\EncryptDatabaseCommand::class,
        'SpecShaper\EncryptBundle\Command\GenKeyCommand' => Kyzegs\DoctrineEncryptionBundle\Command\GenKeyCommand::class,
        'SpecShaper\EncryptBundle\DependencyInjection\Configuration' => Kyzegs\DoctrineEncryptionBundle\DependencyInjection\Configuration::class,
        'SpecShaper\EncryptBundle\DependencyInjection\SpecShaperEncryptExtension' => Kyzegs\DoctrineEncryptionBundle\DependencyInjection\DoctrineEncryptionExtension::class,
        'SpecShaper\EncryptBundle\Encryptors\AesCbcEncryptor' => Kyzegs\DoctrineEncryptionBundle\Encryptors\AesCbcEncryptor::class,
        'SpecShaper\EncryptBundle\Encryptors\AesGcmEncryptor' => Kyzegs\DoctrineEncryptionBundle\Encryptors\AesGcmEncryptor::class,
        'SpecShaper\EncryptBundle\Encryptors\EncryptorFactory' => Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorFactory::class,
        'SpecShaper\EncryptBundle\Encryptors\EncryptorInterface' => Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface::class,
        'SpecShaper\EncryptBundle\Event\EncryptEvent' => Kyzegs\DoctrineEncryptionBundle\Event\EncryptEvent::class,
        'SpecShaper\EncryptBundle\Event\EncryptEventInterface' => Kyzegs\DoctrineEncryptionBundle\Event\EncryptEventInterface::class,
        'SpecShaper\EncryptBundle\Event\EncryptEvents' => Kyzegs\DoctrineEncryptionBundle\Event\EncryptEvents::class,
        'SpecShaper\EncryptBundle\Event\EncryptKeyEvent' => Kyzegs\DoctrineEncryptionBundle\Event\EncryptKeyEvent::class,
        'SpecShaper\EncryptBundle\Event\EncryptKeyEvents' => Kyzegs\DoctrineEncryptionBundle\Event\EncryptKeyEvents::class,
        'SpecShaper\EncryptBundle\EventListener\DoctrineEncryptListener' => Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListener::class,
        'SpecShaper\EncryptBundle\EventListener\DoctrineEncryptListenerInterface' => Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListenerInterface::class,
        'SpecShaper\EncryptBundle\EventListener\EncryptEventListener' => Kyzegs\DoctrineEncryptionBundle\EventListener\EncryptEventListener::class,
        'SpecShaper\EncryptBundle\Exception\EncryptException' => Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException::class,
        'SpecShaper\EncryptBundle\SpecShaperEncryptBundle' => Kyzegs\DoctrineEncryptionBundle\DoctrineEncryptionBundle::class,
        'SpecShaper\EncryptBundle\Twig\EncryptExtension' => Kyzegs\DoctrineEncryptionBundle\Twig\EncryptExtension::class,
    ]);
