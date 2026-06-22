<?php

declare(strict_types=1);

use SpecShaper\EncryptBundle\Encryptors\EncryptorInterface;
use SpecShaper\EncryptBundle\Twig\EncryptExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(EncryptExtension::class)
        ->args([service(EncryptorInterface::class)])
        ->tag('twig.extension');
};
