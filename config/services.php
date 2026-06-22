<?php

declare(strict_types=1);

use SpecShaper\EncryptBundle\BlindIndex\BlindIndexMetadataProvider;
use SpecShaper\EncryptBundle\BlindIndex\BlindIndexUpdater;
use SpecShaper\EncryptBundle\Command\BlindIndexDatabaseCommand;
use SpecShaper\EncryptBundle\Command\EncryptDatabaseCommand;
use SpecShaper\EncryptBundle\Command\GenKeyCommand;
use SpecShaper\EncryptBundle\Encryptors\EncryptorFactory;
use SpecShaper\EncryptBundle\Encryptors\EncryptorInterface;
use SpecShaper\EncryptBundle\Hashers\BlindIndexHasherInterface;
use SpecShaper\EncryptBundle\Hashers\HmacBlindIndexHasher;
use SpecShaper\EncryptBundle\Key\KeyProviderInterface;
use SpecShaper\EncryptBundle\Key\StaticKeyProvider;
use SpecShaper\EncryptBundle\Mapping\EncryptedFieldMetadataProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire(false)->autoconfigure(false)->public(false);

    $services->set(KeyProviderInterface::class, StaticKeyProvider::class)
        ->args([
            param('spec_shaper_encrypt.encrypt_key'),
            param('spec_shaper_encrypt.key_id'),
            param('spec_shaper_encrypt.decryption_keys'),
        ]);

    $services->set(EncryptorFactory::class)
        ->args([service('event_dispatcher'), service(KeyProviderInterface::class)]);

    $services->set(EncryptorInterface::class)
        ->factory([service(EncryptorFactory::class), 'createService'])
        ->args([
            null,
            param('spec_shaper_encrypt.default_associated_data'),
            param('spec_shaper_encrypt.encryptor_class'),
        ]);

    $services->set(BlindIndexHasherInterface::class, HmacBlindIndexHasher::class)
        ->args([param('spec_shaper_encrypt.blind_index_key')]);

    $services->set(BlindIndexMetadataProvider::class);
    $services->set(BlindIndexUpdater::class)->args([service(BlindIndexHasherInterface::class)]);
    $services->set(EncryptedFieldMetadataProvider::class)
        ->args([param('spec_shaper_encrypt.annotation_classes')]);

    $services->set(EncryptDatabaseCommand::class)
        ->args([service(EncryptorInterface::class), service('doctrine'), service(EncryptedFieldMetadataProvider::class)])
        ->tag('console.command');

    $services->set(BlindIndexDatabaseCommand::class)
        ->args([service('doctrine'), service(BlindIndexMetadataProvider::class), service(BlindIndexUpdater::class)])
        ->tag('console.command');

    $services->set(GenKeyCommand::class)->tag('console.command');
};
