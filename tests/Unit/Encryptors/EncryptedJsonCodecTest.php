<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Unit\Encryptors;

use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptedJsonCodec;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EncryptedJsonCodecTest extends TestCase
{
    private EncryptedJsonCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new EncryptedJsonCodec(new JsonTestEncryptor());
    }

    /** @param array<mixed> $value */
    #[DataProvider('arrayValues')]
    public function testRoundTripsArrays(array $value): void
    {
        $encrypted = $this->codec->encrypt($value, 'metadata', 'Record:metadata');

        self::assertTrue($this->codec->isEncryptedWrapper($encrypted));
        self::assertSame($value, $this->codec->decrypt($encrypted, 'metadata', 'Record:metadata'));
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function arrayValues(): iterable
    {
        yield 'list' => [['one', 2, true, null]];
        yield 'associative' => [['name' => 'Ada', 'nested' => ['active' => true]]];
        yield 'empty' => [[]];
    }

    public function testLeavesPlaintextAndExistingEncryptedWrapperUnchanged(): void
    {
        $plaintext = ['__doctrine_encrypted' => ['user' => 'value'], 'other' => true];
        self::assertSame($plaintext, $this->codec->decrypt($plaintext, 'metadata', 'Record:metadata'));

        $encrypted = $this->codec->encrypt(['secret' => 'value'], 'metadata', 'Record:metadata');
        self::assertSame($encrypted, $this->codec->encrypt($encrypted, 'metadata', 'Record:metadata'));
    }

    public function testWrapperKeyOrderIsIrrelevant(): void
    {
        $encrypted = [
            '__doctrine_encrypted' => [
                'ciphertext' => 'encrypted:{}<ENC>',
                'version' => 1,
            ],
        ];

        self::assertTrue($this->codec->isEncryptedWrapper($encrypted));
        self::assertSame([], $this->codec->decrypt($encrypted, 'metadata', 'Record:metadata'));
    }

    /** @param array<mixed> $value */
    #[DataProvider('nonWrappers')]
    public function testRequiresExactWrapperStructure(array $value): void
    {
        self::assertFalse($this->codec->isEncryptedWrapper($value));
        self::assertSame($value, $this->codec->decrypt($value, 'metadata', 'Record:metadata'));
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function nonWrappers(): iterable
    {
        yield 'extra outer key' => [[
            '__doctrine_encrypted' => ['version' => 1, 'ciphertext' => 'encrypted:{}<ENC>'],
            'other' => true,
        ]];
        yield 'extra inner key' => [[
            '__doctrine_encrypted' => ['version' => 1, 'ciphertext' => 'encrypted:{}<ENC>', 'other' => true],
        ]];
        yield 'unsupported version' => [[
            '__doctrine_encrypted' => ['version' => 2, 'ciphertext' => 'encrypted:{}<ENC>'],
        ]];
        yield 'string version' => [[
            '__doctrine_encrypted' => ['version' => '1', 'ciphertext' => 'encrypted:{}<ENC>'],
        ]];
        yield 'missing marker' => [[
            '__doctrine_encrypted' => ['version' => 1, 'ciphertext' => 'encrypted:{}'],
        ]];
        yield 'wrong ciphertext type' => [[
            '__doctrine_encrypted' => ['version' => 1, 'ciphertext' => []],
        ]];
    }

    public function testRejectsNonArrayValues(): void
    {
        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('expected array or null');

        $this->codec->encrypt('scalar', 'metadata', 'Record:metadata');
    }

    public function testRejectsObjectValues(): void
    {
        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('expected array or null');

        $this->codec->encrypt(new \stdClass(), 'metadata', 'Record:metadata');
    }

    public function testRejectsNestedObjectValues(): void
    {
        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('arrays cannot contain objects or resources');

        $this->codec->encrypt(['nested' => new \stdClass()], 'metadata', 'Record:metadata');
    }

    public function testRejectsNestedResourceValues(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        try {
            $this->expectException(EncryptException::class);
            $this->expectExceptionMessage('arrays cannot contain objects or resources');

            $this->codec->encrypt(['nested' => $resource], 'metadata', 'Record:metadata');
        } finally {
            fclose($resource);
        }
    }

    public function testRejectsInvalidJsonDuringEncoding(): void
    {
        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('Cannot encode JSON value at Record:metadata');

        $this->codec->encrypt(["invalid\xB1"], 'metadata', 'Record:metadata');
    }

    public function testRejectsInvalidDecryptedJson(): void
    {
        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('Cannot decode JSON value at Record:metadata');

        $this->codec->decrypt([
            '__doctrine_encrypted' => ['version' => 1, 'ciphertext' => 'encrypted:not-json<ENC>'],
        ], 'metadata', 'Record:metadata');
    }

    public function testRejectsDecryptedJsonScalar(): void
    {
        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('decrypted JSON must contain an array');

        $this->codec->decrypt([
            '__doctrine_encrypted' => ['version' => 1, 'ciphertext' => 'encrypted:"scalar"<ENC>'],
        ], 'metadata', 'Record:metadata');
    }
}

final class JsonTestEncryptor implements EncryptorInterface
{
    public function setSecretKey(string $key): void
    {
    }

    public function encrypt(?string $data, ?string $columnName = null): ?string
    {
        return null === $data ? null : 'encrypted:'.$data.'<ENC>';
    }

    public function decrypt(?string $data, ?string $columnName = null): ?string
    {
        return null === $data ? null : substr($data, strlen('encrypted:'), -strlen('<ENC>'));
    }
}
