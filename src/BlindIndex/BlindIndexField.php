<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\BlindIndex;

final readonly class BlindIndexField
{
    public function __construct(
        private string $field,
        private \ReflectionProperty $property,
        private string $sourceField,
        private \ReflectionProperty $sourceProperty,
        private string $normalizer,
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getProperty(): \ReflectionProperty
    {
        return $this->property;
    }

    public function getSourceField(): string
    {
        return $this->sourceField;
    }

    public function getSourceProperty(): \ReflectionProperty
    {
        return $this->sourceProperty;
    }

    public function getNormalizer(): string
    {
        return $this->normalizer;
    }
}
