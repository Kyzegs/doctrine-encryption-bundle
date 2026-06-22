<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Unit\Hashers;

use Kyzegs\DoctrineEncryptionBundle\Annotations\BlindIndex;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Hashers\HmacBlindIndexHasher;
use PHPUnit\Framework\TestCase;

class HmacBlindIndexHasherTest extends TestCase
{
    public function testHashUsesHmacSha256(): void
    {
        $hasher = new HmacBlindIndexHasher('secret');

        $this->assertSame(
            hash_hmac('sha256', 'test@example.com', 'secret'),
            $hasher->hash('test@example.com')
        );
    }

    public function testLowercaseNormalizerTrimsAndLowercasesValue(): void
    {
        $hasher = new HmacBlindIndexHasher('secret');

        $this->assertSame(
            $hasher->hash('test@example.com'),
            $hasher->hash('  Test@Example.COM  ', BlindIndex::NORMALIZE_LOWERCASE)
        );
    }

    public function testNullValueStaysNull(): void
    {
        $hasher = new HmacBlindIndexHasher('secret');

        $this->assertNull($hasher->hash(null));
    }

    public function testUnknownNormalizerThrowsException(): void
    {
        $hasher = new HmacBlindIndexHasher('secret');

        $this->expectException(EncryptException::class);

        $hasher->hash('test@example.com', 'unknown');
    }
}
