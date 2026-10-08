<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Mapping;

/**
 * Resolves properties across a class hierarchy.
 *
 * Reflection hides private properties of parent classes, which is precisely how Doctrine mapped superclasses
 * declare their fields, so neither ReflectionClass::getProperties() nor a plain ReflectionProperty finds them.
 *
 * @internal
 */
final class ReflectionPropertyResolver
{
    /** @param class-string $className */
    public static function find(string $className, string $propertyName): ?\ReflectionProperty
    {
        for ($class = new \ReflectionClass($className); false !== $class; $class = $class->getParentClass()) {
            if ($class->hasProperty($propertyName)) {
                return $class->getProperty($propertyName);
            }
        }

        return null;
    }

    /**
     * Declarations closest to the given class win.
     *
     * @param \ReflectionClass<object> $reflectionClass
     *
     * @return array<string, \ReflectionProperty>
     */
    public static function all(\ReflectionClass $reflectionClass): array
    {
        $properties = [];

        for ($class = $reflectionClass; false !== $class; $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                $properties[$property->getName()] ??= $property;
            }
        }

        return $properties;
    }
}
