<?php

declare(strict_types=1);

use Kyzegs\DoctrineEncryptionBundle\BlindIndex\BlindIndexMetadataProvider;
use Kyzegs\DoctrineEncryptionBundle\BlindIndex\BlindIndexUpdater;
use Kyzegs\DoctrineEncryptionBundle\Command\BlindIndexDatabaseCommand;
use Kyzegs\DoctrineEncryptionBundle\Command\EncryptDatabaseCommand;
use Kyzegs\DoctrineEncryptionBundle\Command\GenKeyCommand;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorFactory;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptedJsonCodec;
use Kyzegs\DoctrineEncryptionBundle\Hashers\BlindIndexHasherInterface;
use Kyzegs\DoctrineEncryptionBundle\Hashers\HmacBlindIndexHasher;
use Kyzegs\DoctrineEncryptionBundle\Key\KeyProviderInterface;
use Kyzegs\DoctrineEncryptionBundle\Key\StaticKeyProvider;
use Kyzegs\DoctrineEncryptionBundle\Mapping\EncryptedFieldMetadataProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire(false)->autoconfigure(false)->public(false);

    $services->set(KeyProviderInterface::class, StaticKeyProvider::class)
        ->args([
            param('doctrine_encryption.encrypt_key'),
            param('doctrine_encryption.key_id'),
            param('doctrine_encryption.decryption_keys'),
        ]);

    $services->set(EncryptorFactory::class)
        ->args([service('event_dispatcher'), service(KeyProviderInterface::class)]);

    $services->set(EncryptorInterface::class)
        ->factory([service(EncryptorFactory::class), 'createService'])
        ->args([
            null,
            param('doctrine_encryption.default_associated_data'),
            param('doctrine_encryption.encryptor_class'),
        ]);

    $services->set(BlindIndexHasherInterface::class, HmacBlindIndexHasher::class)
        ->args([param('doctrine_encryption.blind_index_key')]);

    $services->set(BlindIndexMetadataProvider::class);
    $services->set(BlindIndexUpdater::class)->args([service(BlindIndexHasherInterface::class)]);
    $services->set(EncryptedJsonCodec::class)->args([service(EncryptorInterface::class)]);
    $services->set(EncryptedFieldMetadataProvider::class)
        ->args([param('doctrine_encryption.annotation_classes')]);

    $services->set(EncryptDatabaseCommand::class)
        ->args([service(EncryptorInterface::class), service('doctrine'), service(EncryptedFieldMetadataProvider::class), service(EncryptedJsonCodec::class)])
        ->tag('console.command');

    $services->set(BlindIndexDatabaseCommand::class)
        ->args([service('doctrine'), service(BlindIndexMetadataProvider::class), service(BlindIndexUpdater::class)])
        ->tag('console.command');

    $services->set(GenKeyCommand::class)->tag('console.command');
};
