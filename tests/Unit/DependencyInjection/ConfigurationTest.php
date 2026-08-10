<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Unit\DependencyInjection;

use Kyzegs\DoctrineEncryptionBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    private const ENCRYPT_KEY = 'YBmNcBGfrZoayB+V254wdYa/abvxSUWJsjCtlMc1tRI=';

    public function testDistinctKeysAreAccepted(): void
    {
        $config = $this->process([
            'encrypt_key' => self::ENCRYPT_KEY,
            'blind_index_key' => 'a-distinct-blind-index-key',
        ]);

        self::assertSame('a-distinct-blind-index-key', $config['blind_index_key']);
        self::assertFalse($config['allow_legacy_cbc']);
        self::assertTrue($config['verify_associated_data']);
    }

    public function testMissingBlindIndexKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('A "blind_index_key" is required.');

        $this->process(['encrypt_key' => self::ENCRYPT_KEY]);
    }

    public function testBlindIndexKeyReusingTheEncryptionKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must differ from "encrypt_key"');

        $this->process([
            'encrypt_key' => self::ENCRYPT_KEY,
            'blind_index_key' => self::ENCRYPT_KEY,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
