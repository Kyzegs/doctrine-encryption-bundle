<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Command;

/** @internal */
final class EncryptedDatabaseTable
{
    /** @var array<string, DatabaseEncryptedField> */
    private array $fields = [];

    /** @param array<string, string> $identifiers */
    public function __construct(
        public readonly string $name,
        public readonly array $identifiers,
    ) {
    }

    public function addField(DatabaseEncryptedField $field): void
    {
        $this->fields[$field->field] = $field;
    }

    /** @return array<string, DatabaseEncryptedField> */
    public function fields(): array
    {
        return $this->fields;
    }
}
