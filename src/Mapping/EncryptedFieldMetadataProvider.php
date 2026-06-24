<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;
use Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;

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

            $attributeFormat = $this->getAttributeFormat($attributeProperty);
            $mappingFormat = $this->getMappingFormat($mapping);

            if (null === $attributeFormat && null === $mappingFormat) {
                continue;
            }

            if (null !== $attributeFormat && null !== $mappingFormat && $attributeFormat !== $mappingFormat) {
                throw new EncryptException(sprintf('Encrypted field "%s:%s" declares conflicting formats "%s" and "%s".', $classMetadata->getName(), $fieldName, $attributeFormat, $mappingFormat));
            }

            $format = $attributeFormat ?? $mappingFormat;
            if (Encrypted::FORMAT_JSON === $format && 'json' !== $this->getMappingValue($mapping, 'type')) {
                throw new EncryptException(sprintf('Encrypted JSON field "%s:%s" must use Doctrine type "json".', $classMetadata->getName(), $fieldName));
            }

            $accessor = $this->getAccessor($classMetadata, $fieldName);
            if (null === $accessor || !is_callable([$accessor, 'getValue']) || !is_callable([$accessor, 'setValue'])) {
                continue;
            }

            $encryptedFields[$fieldName] = new EncryptedField(
                $accessor->getValue(...),
                $accessor->setValue(...),
                $format,
            );
        }

        return $encryptedFields;
    }

    public function hasEncryptedAttribute(\ReflectionProperty $property): bool
    {
        return null !== $this->getAttributeFormat($property);
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

    private function getAttributeFormat(\ReflectionProperty $property): ?string
    {
        foreach ($property->getAttributes() as $attribute) {
            if (!in_array($attribute->getName(), $this->attributeClasses, true)) {
                continue;
            }

            $instance = $attribute->newInstance();

            return $instance instanceof Encrypted ? $instance->format : Encrypted::FORMAT_SCALAR;
        }

        return null;
    }

    private function getMappingFormat(mixed $mapping): ?string
    {
        $options = is_array($mapping) ? ($mapping['options'] ?? []) : ($mapping->options ?? []);
        if (!array_key_exists(self::OPTION_NAME, $options)) {
            return null;
        }

        $value = $options[self::OPTION_NAME] ?? false;

        if (in_array($value, [true, 1, '1'], true) || (is_string($value) && 'true' === strtolower($value))) {
            return Encrypted::FORMAT_SCALAR;
        }

        if (is_string($value) && Encrypted::FORMAT_JSON === strtolower($value)) {
            return Encrypted::FORMAT_JSON;
        }

        if (in_array($value, [false, null, 0, '0'], true) || (is_string($value) && 'false' === strtolower($value))) {
            return null;
        }

        $description = is_scalar($value) ? var_export($value, true) : get_debug_type($value);

        throw new EncryptException(sprintf('Unsupported encrypted field mapping format %s.', $description));
    }
}
