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

            if (null !== $property && ($this->hasEncryptedAttribute($property) || $this->hasEncryptedMappingOption($classMetadata, $fieldName))) {
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

    private function hasEncryptedMappingOption(ClassMetadata $classMetadata, string $fieldName): bool
    {
        $mapping = $classMetadata->getFieldMapping($fieldName);
        $options = is_array($mapping) ? ($mapping['options'] ?? []) : ($mapping->options ?? []);
        $value = $options[self::OPTION_NAME] ?? false;

        return in_array($value, [true, 1, '1'], true)
            || (is_string($value) && 'true' === strtolower($value));
    }
}
