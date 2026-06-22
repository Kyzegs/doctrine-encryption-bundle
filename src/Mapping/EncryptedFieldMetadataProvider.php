<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;

final readonly class EncryptedFieldMetadataProvider
{
    public const OPTION_NAME = 'encrypted';

    /** @param list<class-string> $attributeClasses */
    public function __construct(private array $attributeClasses)
    {
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     *
     * @return array<string, EncryptedField>
     */
    public function getForClassMetadata(ClassMetadata $classMetadata): array
    {
        $encryptedFields = [];

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            $mapping = $classMetadata->getFieldMapping($fieldName);
            $attributeProperty = $this->getOriginalProperty($classMetadata, $fieldName, $mapping);

            if (!$this->hasEncryptedAttribute($attributeProperty) && !$this->hasEncryptedMappingOption($mapping)) {
                continue;
            }

            $accessor = $this->getAccessor($classMetadata, $fieldName);
            if (null === $accessor || !is_callable([$accessor, 'getValue']) || !is_callable([$accessor, 'setValue'])) {
                continue;
            }

            $encryptedFields[$fieldName] = new EncryptedField(
                $accessor->getValue(...),
                $accessor->setValue(...),
            );
        }

        return $encryptedFields;
    }

    public function hasEncryptedAttribute(\ReflectionProperty $property): bool
    {
        foreach ($property->getAttributes() as $attribute) {
            if (in_array($attribute->getName(), $this->attributeClasses, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param ClassMetadata<object> $classMetadata */
    private function getOriginalProperty(ClassMetadata $classMetadata, string $fieldName, mixed $mapping): \ReflectionProperty
    {
        $originalClass = $this->getMappingValue($mapping, 'originalClass') ?? $classMetadata->getName();
        $originalField = $this->getMappingValue($mapping, 'originalField') ?? $fieldName;

        return new \ReflectionProperty($originalClass, $originalField);
    }

    /** @param ClassMetadata<object> $classMetadata */
    private function getAccessor(ClassMetadata $classMetadata, string $fieldName): ?object
    {
        $accessor = $this->getModernAccessor($classMetadata, $fieldName);
        if (null !== $accessor) {
            return $accessor;
        }

        return $classMetadata->getReflectionProperty($fieldName);
    }

    private function getModernAccessor(object $classMetadata, string $fieldName): ?object
    {
        if (!method_exists($classMetadata, 'getPropertyAccessor')) {
            return null;
        }

        $accessor = $classMetadata->getPropertyAccessor($fieldName);

        return is_object($accessor) ? $accessor : null;
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
