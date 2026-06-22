<?php

namespace SpecShaper\EncryptBundle\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionProperty;

final class EncryptedFieldMetadataProvider
{
    public const OPTION_NAME = 'encrypted';

    /**
     * @param list<class-string> $attributeClasses
     */
    public function __construct(private readonly array $attributeClasses)
    {
    }

    /**
     * @return array<string, ReflectionProperty>
     */
    public function getForClassMetadata(ClassMetadata $classMetadata): array
    {
        $encryptedFields = [];

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            $property = $classMetadata->getReflectionProperty($fieldName);
            $mapping = $classMetadata->getFieldMapping($fieldName);
            $attributeProperty = $this->getOriginalProperty($classMetadata, $fieldName, $mapping);

            if (null !== $property && ($this->hasEncryptedAttribute($attributeProperty) || $this->hasEncryptedMappingOption($mapping))) {
                $encryptedFields[$fieldName] = $property;
            }
        }

        return $encryptedFields;
    }

    public function hasEncryptedAttribute(ReflectionProperty $property): bool
    {
        foreach ($property->getAttributes() as $attribute) {
            if (in_array($attribute->getName(), $this->attributeClasses, true)) {
                return true;
            }
        }

        return false;
    }

    private function getOriginalProperty(ClassMetadata $classMetadata, string $fieldName, mixed $mapping): ReflectionProperty
    {
        $originalClass = $this->getMappingValue($mapping, 'originalClass') ?? $classMetadata->getName();
        $originalField = $this->getMappingValue($mapping, 'originalField') ?? $fieldName;

        return new ReflectionProperty($originalClass, $originalField);
    }

    private function getMappingValue(mixed $mapping, string $key): mixed
    {
        return is_array($mapping) ? ($mapping[$key] ?? null) : ($mapping->$key ?? null);
    }

    private function hasEncryptedMappingOption(mixed $mapping): bool
    {
        $options = is_array($mapping) ? ($mapping['options'] ?? []) : ($mapping->options ?? []);
        $value = $options[self::OPTION_NAME] ?? false;

        return in_array($value, [true, 1, '1'], true)
            || (is_string($value) && 'true' === strtolower($value));
    }
}
