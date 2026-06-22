<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\BlindIndex;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Kyzegs\DoctrineEncryptionBundle\Attribute\BlindIndex;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;

final class BlindIndexMetadataProvider
{
    /**
     * @var array<string, array<string, BlindIndexField>>
     */
    private array $cache = [];

    /**
     * @return array<class-string, array<string, BlindIndexField>>
     */
    public function getAllForObjectManager(ObjectManager $objectManager): array
    {
        $blindIndexFields = [];

        /** @var list<ClassMetadata<object>> $metadata */
        $metadata = $objectManager->getMetadataFactory()->getAllMetadata();

        foreach ($metadata as $classMeta) {
            if ($classMeta->isMappedSuperclass) {
                continue;
            }

            $fields = $this->getForClassMetadata($classMeta);

            if ([] !== $fields) {
                $blindIndexFields[$classMeta->getName()] = $fields;
            }
        }

        return $blindIndexFields;
    }

    /**
     * @return array<string, BlindIndexField>
     */
    public function getForEntity(ObjectManager $objectManager, object $entity): array
    {
        /** @var ClassMetadata<object> $classMeta */
        $classMeta = $objectManager->getClassMetadata($entity::class);

        return $this->getForClassMetadata($classMeta);
    }

    /**
     * @param ClassMetadata<object> $classMeta
     *
     * @return array<string, BlindIndexField>
     */
    public function getForClassMetadata(ClassMetadata $classMeta): array
    {
        $className = $classMeta->getName();

        if (isset($this->cache[$className])) {
            return $this->cache[$className];
        }

        $reflectionClass = new \ReflectionClass($className);
        $properties = [];
        foreach ($reflectionClass->getProperties() as $property) {
            $properties[$property->getName()] = $property;
        }
        $blindIndexFields = [];

        foreach ($reflectionClass->getProperties() as $refProperty) {
            foreach ($refProperty->getAttributes(BlindIndex::class, \ReflectionAttribute::IS_INSTANCEOF) as $refAttribute) {
                /** @var BlindIndex $attribute */
                $attribute = $refAttribute->newInstance();
                $field = $refProperty->getName();
                $sourceField = $attribute->getSourceField();

                if (!$classMeta->hasField($sourceField)) {
                    throw new EncryptException(sprintf('Blind index source field "%s" is not mapped on "%s".', $sourceField, $className));
                }

                if (!$classMeta->hasField($field)) {
                    throw new EncryptException(sprintf('Blind index field "%s" is not mapped on "%s".', $field, $className));
                }

                $blindIndexFields[$field] = new BlindIndexField(
                    $field,
                    $properties[$field],
                    $sourceField,
                    $properties[$sourceField],
                    $attribute->getNormalizer()
                );
            }
        }

        $this->cache[$className] = $blindIndexFields;

        return $blindIndexFields;
    }
}
