<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\BlindIndex;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Hashers\BlindIndexHasherInterface;

/**
 * Builds blind-index lookups from the mapping instead of from a normalizer the caller has to remember.
 *
 * Hashing a search term by hand works only while the normalizer passed at the call site stays identical to
 * the one on the attribute; the two drift silently, and the query then returns nothing.
 */
final readonly class BlindIndexQueryHelper
{
    public function __construct(
        private ManagerRegistry $registry,
        private BlindIndexMetadataProvider $metadataProvider,
        private BlindIndexHasherInterface $hasher,
    ) {
    }

    /**
     * Hashes a value the way the named blind-index field is hashed.
     *
     * @param class-string $className
     */
    public function hash(string $className, string $blindIndexField, ?string $value): ?string
    {
        $fields = $this->getBlindIndexFields($className);

        if (!isset($fields[$blindIndexField])) {
            throw new EncryptException(sprintf('"%s" is not a blind index on "%s".', $blindIndexField, $className));
        }

        return $this->hasher->hash($value, $fields[$blindIndexField]->getNormalizer());
    }

    /**
     * Builds findBy()/findOneBy() criteria from the encrypted field a caller actually searches on.
     *
     * A source field carrying several blind indexes yields one criterion each, which narrows the result
     * rather than widening it.
     *
     * @param class-string $className
     *
     * @return array<string, string|null>
     */
    public function criteria(string $className, string $sourceField, ?string $value): array
    {
        $criteria = [];

        foreach ($this->getBlindIndexFields($className) as $field => $blindIndex) {
            if ($blindIndex->getSourceField() === $sourceField) {
                $criteria[$field] = $this->hasher->hash($value, $blindIndex->getNormalizer());
            }
        }

        if ([] === $criteria) {
            throw new EncryptException(sprintf('No blind index on "%s" indexes "%s".', $className, $sourceField));
        }

        return $criteria;
    }

    /**
     * @param class-string $className
     *
     * @return array<string, BlindIndexField>
     */
    private function getBlindIndexFields(string $className): array
    {
        $manager = $this->registry->getManagerForClass($className);

        if (!$manager instanceof ObjectManager) {
            throw new EncryptException(sprintf('"%s" is not managed by any Doctrine manager.', $className));
        }

        $classMetadata = $manager->getClassMetadata($className);

        if (!$classMetadata instanceof ClassMetadata) {
            throw new EncryptException(sprintf('"%s" is not mapped by the Doctrine ORM.', $className));
        }

        return $this->metadataProvider->getForClassMetadata($classMetadata);
    }
}
