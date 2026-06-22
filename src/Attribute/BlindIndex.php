<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class BlindIndex
{
    public const NORMALIZE_NONE = 'none';
    public const NORMALIZE_TRIM = 'trim';
    public const NORMALIZE_LOWERCASE = 'lowercase';
    public const NORMALIZE_UPPERCASE = 'uppercase';

    public function __construct(
        private readonly string $sourceField,
        private readonly string $normalizer = self::NORMALIZE_NONE,
    ) {
    }

    public function getSourceField(): string
    {
        return $this->sourceField;
    }

    public function getNormalizer(): string
    {
        return $this->normalizer;
    }
}
