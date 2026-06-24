<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Encryptors;

final readonly class DecodedCiphertextEnvelope
{
    public function __construct(
        public string $algorithm,
        public string $keyId,
        public string $associatedData,
        public string $payload,
    ) {
    }

    /** @return array{algorithm: string, key_id: string, associated_data: string, payload: string} */
    public function toArray(): array
    {
        return [
            'algorithm' => $this->algorithm,
            'key_id' => $this->keyId,
            'associated_data' => $this->associatedData,
            'payload' => $this->payload,
        ];
    }
}
