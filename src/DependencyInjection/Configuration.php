<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\DependencyInjection;

use Kyzegs\DoctrineEncryptionBundle\Annotations\Encrypted as LegacyEncrypted;
use Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\AesGcmEncryptor;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface;
use Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListener;
use Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListenerInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('doctrine_encryption');

        self::configure($treeBuilder->getRootNode());

        return $treeBuilder;
    }

    public static function configure(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->scalarNode('encrypt_key')->defaultNull()->end()
                ->scalarNode('key_provider_service')->defaultNull()->end()
                ->scalarNode('key_id')->defaultValue('default')->cannotBeEmpty()->end()
                ->arrayNode('decryption_keys')
                    ->useAttributeAsKey('id')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('blind_index_key')->defaultValue(null)->end()
                ->scalarNode('default_associated_data')->defaultValue(null)->end()
                ->booleanNode('allow_legacy_cbc')
                    ->defaultFalse()
                    ->info('Permit reads of unauthenticated AES-CBC ciphertext while migrating. Rotate, then disable.')
                ->end()
                ->booleanNode('verify_associated_data')
                    ->defaultTrue()
                    ->info('Require a ciphertext to be read from the field it was written to. Disable only while renaming a field, then rotate.')
                ->end()
                ->scalarNode('listener_class')
                    ->defaultValue(DoctrineEncryptListener::class)
                    ->validate()
                        ->ifTrue(static fn (mixed $class): bool => !is_string($class) || !is_a($class, DoctrineEncryptListenerInterface::class, true))
                        ->thenInvalid('The listener class must implement '.DoctrineEncryptListenerInterface::class.'.')
                    ->end()
                ->end()
                ->scalarNode('encryptor_class')
                    ->defaultValue(AesGcmEncryptor::class)
                    ->validate()
                        ->ifTrue(static fn (mixed $class): bool => !is_string($class) || !is_a($class, EncryptorInterface::class, true))
                        ->thenInvalid('The encryptor class must implement '.EncryptorInterface::class.'.')
                    ->end()
                ->end()
                ->scalarNode('encryptor_service')->defaultNull()->end()
                ->booleanNode('is_disabled')->defaultFalse()->end()
                ->arrayNode('connections')
                ->treatNullLike([])
                ->prototype('scalar')->end()
                ->defaultValue([
                    'default',
                ])
                ->end()
                ->arrayNode('annotation_classes')
                ->treatNullLike([])
                ->prototype('scalar')->end()
                ->defaultValue([
                    Encrypted::class,
                    LegacyEncrypted::class,
                ])
                ->end()
                ->booleanNode('enable_twig')
                ->defaultTrue()
                ->info('Enable or disable Twig functionality')
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => empty($config['encrypt_key']) && empty($config['key_provider_service']))
                ->thenInvalid('Configure either "encrypt_key" or "key_provider_service".')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => empty($config['blind_index_key']))
                ->thenInvalid('A "blind_index_key" is required. It must differ from the encryption key.')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => !empty($config['blind_index_key']) && $config['blind_index_key'] === $config['encrypt_key'])
                ->thenInvalid('The "blind_index_key" must differ from "encrypt_key". Reusing one key for AES and HMAC is unsafe.')
            ->end()
        ;
    }
}
