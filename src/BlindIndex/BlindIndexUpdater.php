<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\BlindIndex;

use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Hashers\BlindIndexHasherInterface;

final readonly class BlindIndexUpdater
{
    public function __construct(
        private BlindIndexHasherInterface $blindIndexHasher,
    ) {
    }

    /**
     * @param array<string, BlindIndexField> $blindIndexFields
     * @param array<string, mixed>|null      $changedFields
     */
    public function update(object $entity, array $blindIndexFields, ?array $changedFields = null): bool
    {
        $hasUpdated = false;

        foreach ($blindIndexFields as $blindIndexField) {
            if (null !== $changedFields && !array_key_exists($blindIndexField->getSourceField(), $changedFields)) {
                continue;
            }

            $sourceValue = $blindIndexField->getSourceProperty()->getValue($entity);

            if (is_object($sourceValue)) {
                throw new EncryptException(sprintf('Cannot create blind index from an object at %s:%s', $entity::class, $blindIndexField->getSourceField()));
            }

            $blindIndexField->getProperty()->setValue(
                $entity,
                $this->blindIndexHasher->hash($sourceValue, $blindIndexField->getNormalizer())
            );

            $hasUpdated = true;
        }

        return $hasUpdated;
    }
}
