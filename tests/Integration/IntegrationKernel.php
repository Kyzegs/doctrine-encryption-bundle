<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Kyzegs\DoctrineEncryptionBundle\DoctrineEncryptionBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

final class IntegrationKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new DoctrineBundle(), new TwigBundle(), new DoctrineEncryptionBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function ($container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
            ]);
            $container->loadFromExtension('doctrine', [
                'dbal' => ['url' => 'sqlite:///:memory:'],
                'orm' => [
                    'auto_mapping' => false,
                    'mappings' => [
                        'DoctrineEncryptionBundleTests' => [
                            'type' => 'attribute',
                            'dir' => __DIR__.'/Fixture',
                            'prefix' => 'Kyzegs\\DoctrineEncryptionBundle\\Tests\\Integration\\Fixture',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
            $container->loadFromExtension('doctrine_encryption', [
                'encrypt_key' => 'YBmNcBGfrZoayB+V254wdYa/abvxSUWJsjCtlMc1tRI=',
                'blind_index_key' => 'a-distinct-blind-index-test-key',
                'key_id' => 'integration',
            ]);
        });
    }

    public function getCacheDir(): string
    {
        // The kernel is not booted in debug mode, so nothing invalidates a compiled container on its own.
        $sources = array_merge(
            [__FILE__, __DIR__.'/../../config/services.php', __DIR__.'/../../src/DoctrineEncryptionBundle.php'],
            glob(__DIR__.'/Fixture/*.php') ?: [],
        );
        $fingerprint = implode('', array_map(static fn (string $file): string => (string) md5_file($file), $sources));

        return sys_get_temp_dir().'/kyzegs-doctrine-encryption-bundle/cache-'.md5($fingerprint);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/kyzegs-doctrine-encryption-bundle/log';
    }
}
