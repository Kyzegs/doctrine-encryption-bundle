<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Hashers;

use Kyzegs\DoctrineEncryptionBundle\Attribute\BlindIndex;

interface BlindIndexHasherInterface
{
    public function hash(?string $value, string $normalizer = BlindIndex::NORMALIZE_NONE): ?string;
}
