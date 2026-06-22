<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Tests\Unit\Key;

use PHPUnit\Framework\TestCase;
use SpecShaper\EncryptBundle\Exception\EncryptException;
use SpecShaper\EncryptBundle\Key\StaticKeyProvider;

final class StaticKeyProviderTest extends TestCase
{
    public function testReturnsCurrentAndRetiredBinaryKeys(): void
    {
        $current = base64_encode(str_repeat('A', 32));
        $retired = base64_encode(str_repeat('B', 32));
        $provider = new StaticKeyProvider($current, 'current', ['retired' => $retired]);

        self::assertSame('current', $provider->currentKeyId());
        self::assertSame(str_repeat('A', 32), $provider->currentKey());
        self::assertSame(str_repeat('B', 32), $provider->key('retired'));
    }

    public function testRejectsInvalidBase64Key(): void
    {
        $this->expectException(EncryptException::class);
        new StaticKeyProvider('not-a-key');
    }

    public function testRejectsUnknownKeyId(): void
    {
        $provider = new StaticKeyProvider(base64_encode(str_repeat('A', 32)));

        $this->expectException(EncryptException::class);
        $provider->key('missing');
    }
}
