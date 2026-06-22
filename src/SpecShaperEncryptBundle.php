<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle;

use SpecShaper\EncryptBundle\DependencyInjection\Configuration;
use SpecShaper\EncryptBundle\DependencyInjection\SpecShaperEncryptExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SpecShaperEncryptBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        Configuration::configure($definition->rootNode());
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        SpecShaperEncryptExtension::loadProcessedConfig($config, $container);
    }
}
