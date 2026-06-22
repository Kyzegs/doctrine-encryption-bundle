<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Mapping;

final readonly class EncryptedField
{
    /**
     * @param \Closure(object): mixed       $reader
     * @param \Closure(object, mixed): void $writer
     */
    public function __construct(
        private \Closure $reader,
        private \Closure $writer,
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
}
