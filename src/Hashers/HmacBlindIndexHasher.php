<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Hashers;

use Kyzegs\DoctrineEncryptionBundle\Attribute\BlindIndex;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;

final readonly class HmacBlindIndexHasher implements BlindIndexHasherInterface
{
    public function __construct(
        private string $key,
    ) {
    }

    public function hash(?string $value, string $normalizer = BlindIndex::NORMALIZE_NONE): ?string
    {
        if (null === $value) {
            return null;
        }

        return hash_hmac('sha256', $this->normalize($value, $normalizer), $this->key);
    }

    private function normalize(string $value, string $normalizer): string
    {
        return match ($normalizer) {
            BlindIndex::NORMALIZE_NONE => $value,
            BlindIndex::NORMALIZE_TRIM => trim($value),
            BlindIndex::NORMALIZE_LOWERCASE => mb_strtolower(trim($value)),
            BlindIndex::NORMALIZE_UPPERCASE => mb_strtoupper(trim($value)),
            default => throw new EncryptException(sprintf('Unknown blind index normalizer "%s".', $normalizer)),
        };
    }
}
