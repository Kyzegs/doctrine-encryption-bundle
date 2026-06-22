<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\DependencyInjection;

use SpecShaper\EncryptBundle\Encryptors\EncryptorInterface;
use SpecShaper\EncryptBundle\Event\EncryptEvents;
use SpecShaper\EncryptBundle\EventListener\DoctrineEncryptListener;
use SpecShaper\EncryptBundle\EventListener\DoctrineEncryptListenerInterface;
use SpecShaper\EncryptBundle\EventListener\EncryptEventListener;
use SpecShaper\EncryptBundle\Key\KeyProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @see http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
final class SpecShaperEncryptExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        self::loadProcessedConfig($config, $container);
    }

    /** @param array<string, mixed> $config */
    public static function loadProcessedConfig(array $config, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        if ($container->hasParameter('encrypt_key')) {
            trigger_deprecation('SpecShaperEncryptBundle', 'v3.0.2', 'storing Specshaper Encrypt Key in parameters is deprecated. Move to Config/Packages/spec_shaper_encrypt.yaml');
            $encryptKey = $container->getParameter('encrypt_key');
        } else {
            $encryptKey = $config['encrypt_key'];
        }

        $blindIndexKey = $config['blind_index_key'] ?? $encryptKey;

        $container->setParameter('spec_shaper_encrypt.encrypt_key', $encryptKey);
        $container->setParameter('spec_shaper_encrypt.key_id', $config['key_id']);
        $container->setParameter('spec_shaper_encrypt.decryption_keys', $config['decryption_keys']);
        $container->setParameter('spec_shaper_encrypt.blind_index_key', $blindIndexKey);
        $container->setParameter('spec_shaper_encrypt.default_associated_data', $config['default_associated_data']);
        $container->setParameter('spec_shaper_encrypt.listener_class', $config['listener_class']);
        $container->setParameter('spec_shaper_encrypt.encryptor_class', $config['encryptor_class']);
        $container->setParameter('spec_shaper_encrypt.annotation_classes', $config['annotation_classes']);
        $container->setParameter('spec_shaper_encrypt.is_disabled', $config['is_disabled']);

        if (null !== $config['key_provider_service']) {
            $container->removeDefinition(KeyProviderInterface::class);
            $container->setAlias(KeyProviderInterface::class, $config['key_provider_service']);
        }

        if (null !== $config['encryptor_service']) {
            $container->removeDefinition(EncryptorInterface::class);
            $container->setAlias(EncryptorInterface::class, $config['encryptor_service']);
        }

        $doctrineListener = new Definition($config['listener_class']);
        $doctrineListener
            ->setAutowired(true)
            ->setArgument('$isDisabled', $config['is_disabled'])
        ;

        $listenerConstructor = (new \ReflectionClass($config['listener_class']))->getConstructor();
        foreach ($listenerConstructor?->getParameters() ?? [] as $parameter) {
            if ('annotationArray' === $parameter->getName()) {
                $doctrineListener->setArgument('$annotationArray', $config['annotation_classes']);
                break;
            }
        }

        $encryptEventListener = new Definition(EncryptEventListener::class);
        $encryptEventListener
            ->setAutowired(true)
            ->setArgument('$isDisabled', $config['is_disabled'])
        ;

        foreach ($config['connections'] as $connectionName) {
            $doctrineListener->addTag('doctrine.event_listener', [
                'event' => 'postLoad',
                'priority' => 500,
                'connection' => $connectionName,
            ]);

            $doctrineListener->addTag('doctrine.event_listener', [
                'event' => 'postUpdate',
                'priority' => 500,
                'connection' => $connectionName,
            ]);

            if (method_exists($config['listener_class'], 'postPersist')) {
                $doctrineListener->addTag('doctrine.event_listener', [
                    'event' => 'postPersist',
                    'priority' => 500,
                    'connection' => $connectionName,
                ]);
            }

            $doctrineListener->addTag('doctrine.event_listener', [
                'event' => 'onFlush',
                'priority' => 500,
                'connection' => $connectionName,
            ]);
        }

        $encryptEventListener->addTag('kernel.event_listener', [
            'event' => EncryptEvents::ENCRYPT,
            'method' => 'encrypt',
        ]);
        $encryptEventListener->addTag('kernel.event_listener', [
            'event' => EncryptEvents::DECRYPT,
            'method' => 'decrypt',
        ]);
        $encryptEventListener->addTag('kernel.event_listener', [
            'event' => EncryptEvents::LEGACY_ENCRYPT,
            'method' => 'encrypt',
        ]);
        $encryptEventListener->addTag('kernel.event_listener', [
            'event' => EncryptEvents::LEGACY_DECRYPT,
            'method' => 'decrypt',
        ]);

        $container->addDefinitions([
            DoctrineEncryptListenerInterface::class => $doctrineListener,
            EncryptEventListener::class => $encryptEventListener,
        ]);
        $container->setAlias(DoctrineEncryptListener::class, DoctrineEncryptListenerInterface::class);

        // Check if Twig is available
        if ($config['enable_twig'] && class_exists(\Twig\Environment::class)) {
            $loader->load('twig_services.php');
        }
    }
}
