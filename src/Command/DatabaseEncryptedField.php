<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Command;

/** @internal */
final readonly class DatabaseEncryptedField
{
    public function __construct(
        public string $field,
        public string $column,
        public string $format,
    ) {
    }
}
