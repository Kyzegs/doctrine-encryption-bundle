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
                ->ifTrue(static fn (array $config): bool => !empty($config['key_provider_service']) && empty($config['blind_index_key']))
                ->thenInvalid('A distinct "blind_index_key" is required when using a custom key provider.')
            ->end()
        ;
    }
}
