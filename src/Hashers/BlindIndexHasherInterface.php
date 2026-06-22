<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Hashers;

use SpecShaper\EncryptBundle\Attribute\BlindIndex;

interface BlindIndexHasherInterface
{
    public function hash(?string $value, string $normalizer = BlindIndex::NORMALIZE_NONE): ?string;
}
