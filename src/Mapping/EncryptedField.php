<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Mapping;

use Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted;

final readonly class EncryptedField
{
    /**
     * @param \Closure(object): mixed       $reader
     * @param \Closure(object, mixed): void $writer
     */
    public function __construct(
        private \Closure $reader,
        private \Closure $writer,
        private string $format = Encrypted::FORMAT_SCALAR,
    ) {
    }

    public function getValue(object $entity): mixed
    {
        return ($this->reader)($entity);
    }

    public function setValue(object $entity, mixed $value): void
    {
        ($this->writer)($entity, $value);
    }

    public function getFormat(): string
    {
        return $this->format;
    }
}
