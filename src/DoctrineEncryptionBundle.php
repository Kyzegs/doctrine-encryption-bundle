<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle;

use Kyzegs\DoctrineEncryptionBundle\DependencyInjection\Configuration;
use Kyzegs\DoctrineEncryptionBundle\DependencyInjection\DoctrineEncryptionExtension;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface;
use Kyzegs\DoctrineEncryptionBundle\Types\EncryptedTextType;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class DoctrineEncryptionBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        Configuration::configure($definition->rootNode());
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        DoctrineEncryptionExtension::loadProcessedConfig($config, $container);
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        // Prepended rather than appended, so an application declaring the same type name keeps its own.
        $container->prependExtensionConfig('doctrine', [
            'dbal' => ['types' => [EncryptedTextType::NAME => EncryptedTextType::class]],
        ]);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->container instanceof ContainerInterface && $this->container->has(EncryptorInterface::class)) {
            $encryptor = $this->container->get(EncryptorInterface::class);
            EncryptedTextType::setEncryptor($encryptor instanceof EncryptorInterface ? $encryptor : null);
        }
    }

    public function shutdown(): void
    {
        EncryptedTextType::setEncryptor(null);

        parent::shutdown();
    }
}
