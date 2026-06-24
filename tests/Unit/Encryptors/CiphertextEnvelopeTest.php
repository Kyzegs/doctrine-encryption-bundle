<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Unit\Encryptors;

use Kyzegs\DoctrineEncryptionBundle\Encryptors\CiphertextEnvelope;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\DecodedCiphertextEnvelope;
use PHPUnit\Framework\TestCase;

final class CiphertextEnvelopeTest extends TestCase
{
    public function testTypedAndLegacyDecodersExposeTheSameEnvelope(): void
    {
        $ciphertext = CiphertextEnvelope::encode('gcm', 'primary-2026', 'customer.email', "\0binary");

        $envelope = CiphertextEnvelope::decodeValue($ciphertext);

        self::assertInstanceOf(DecodedCiphertextEnvelope::class, $envelope);
        self::assertSame('gcm', $envelope->algorithm);
        self::assertSame('primary-2026', $envelope->keyId);
        self::assertSame('customer.email', $envelope->associatedData);
        self::assertSame("\0binary", $envelope->payload);
        self::assertSame($envelope->toArray(), CiphertextEnvelope::decode($ciphertext));
    }

    public function testBothDecodersIgnoreLegacyCiphertext(): void
    {
        self::assertNull(CiphertextEnvelope::decodeValue('c2VjcmV0<ENC>'));
        self::assertNull(CiphertextEnvelope::decode('c2VjcmV0<ENC>'));
    }
}
