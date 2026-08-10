<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Unit\Types;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\AesGcmEncryptor;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Types\EncryptedTextType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EncryptedTextTypeTest extends TestCase
{
    private const TEST_KEY = 'YBmNcBGfrZoayB+V254wdYa/abvxSUWJsjCtlMc1tRI=';

    protected function tearDown(): void
    {
        EncryptedTextType::setEncryptor(null);
        parent::tearDown();
    }

    public function testNullPassesThroughWithoutAnEncryptor(): void
    {
        $type = new EncryptedTextType();

        self::assertNull($type->convertToDatabaseValue(null, new SQLitePlatform()));
        self::assertNull($type->convertToPHPValue(null, new SQLitePlatform()));
    }

    public function testRoundTrip(): void
    {
        EncryptedTextType::setEncryptor($this->createEncryptor());
        $type = new EncryptedTextType();
        $platform = new SQLitePlatform();

        $ciphertext = $type->convertToDatabaseValue('secret', $platform);
        self::assertIsString($ciphertext);
        self::assertStringStartsWith('SSEB1:gcm:', $ciphertext);
        self::assertSame('secret', $type->convertToPHPValue($ciphertext, $platform));
    }

    public function testWithoutAnEncryptorTheFailureNamesTheCause(): void
    {
        $type = new EncryptedTextType();

        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('has no encryptor');
        $type->convertToDatabaseValue('secret', new SQLitePlatform());
    }

    public function testNonStringableValuesAreRejected(): void
    {
        EncryptedTextType::setEncryptor($this->createEncryptor());
        $type = new EncryptedTextType();

        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('cannot encrypt a value of type array');
        $type->convertToDatabaseValue(['nope'], new SQLitePlatform());
    }

    private function createEncryptor(): AesGcmEncryptor
    {
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);

        return $encryptor;
    }
}
