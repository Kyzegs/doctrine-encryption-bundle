<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Kyzegs\DoctrineEncryptionBundle\BlindIndex\BlindIndexMetadataProvider;
use Kyzegs\DoctrineEncryptionBundle\BlindIndex\BlindIndexUpdater;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Mapping\EncryptedField;
use Kyzegs\DoctrineEncryptionBundle\Mapping\EncryptedFieldMetadataProvider;

class DoctrineEncryptListener implements DoctrineEncryptListenerInterface
{
    public const ENCRYPTOR_INTERFACE_NS = EncryptorInterface::class;

    /** @var array<class-string, array<string, EncryptedField>> */
    protected array $encryptedFieldCache = [];

    /** @var array<int, array<string, scalar|null>> */
    private array $rawValues = [];

    public function __construct(
        private readonly EncryptorInterface $encryptor,
        private bool $isDisabled,
        private readonly BlindIndexMetadataProvider $blindIndexMetadataProvider,
        private readonly BlindIndexUpdater $blindIndexUpdater,
        private readonly EncryptedFieldMetadataProvider $encryptedFieldMetadataProvider,
    ) {
    }

    public function getEncryptor(): EncryptorInterface
    {
        return $this->encryptor;
    }

    public function setIsDisabled(?bool $isDisabled = true): DoctrineEncryptListenerInterface
    {
        $this->isDisabled = $isDisabled ?? false;

        return $this;
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->isDisabled) {
            return;
        }

        $objectManager = $args->getObjectManager();
        $unitOfWork = $objectManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->processFields($objectManager, $entity, true);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->processFields($objectManager, $entity, true);
        }
    }

    /** @param LifecycleEventArgs<EntityManagerInterface> $args */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $this->processFields($args->getObjectManager(), $args->getObject(), false);
    }

    /** @param LifecycleEventArgs<EntityManagerInterface> $args */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->restoreRawValues($args);
    }

    /** @param LifecycleEventArgs<EntityManagerInterface> $args */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->restoreRawValues($args);
    }

    public function decryptValue(?string $value, ?string $columnName): ?string
    {
        return $this->encryptor->decrypt($value, $columnName);
    }

    /** @param list<\ReflectionProperty> $allProperties
     * @return list<\ReflectionProperty>
     */
    public function getEncryptionableProperties(array $allProperties): array
    {
        return array_values(array_filter(
            $allProperties,
            $this->encryptedFieldMetadataProvider->hasEncryptedAttribute(...),
        ));
    }

    protected function processFields(EntityManagerInterface $objectManager, object $entity, bool $encrypt): bool
    {
        $properties = $this->getEncryptedFields($objectManager, $entity);
        $blindIndexes = $this->blindIndexMetadataProvider->getForEntity($objectManager, $entity);

        if ([] === $properties && [] === $blindIndexes) {
            return false;
        }

        $unitOfWork = $objectManager->getUnitOfWork();
        $objectId = spl_object_id($entity);
        $metadata = $objectManager->getClassMetadata($entity::class);
        $changeSet = $unitOfWork->getEntityChangeSet($entity);
        $blindIndexesUpdated = false;

        if ($encrypt && [] !== $blindIndexes) {
            $blindIndexesUpdated = $this->blindIndexUpdater->update($entity, $blindIndexes, $changeSet);
        }

        foreach ($properties as $field => $property) {
            $value = $property->getValue($entity);

            if (is_object($value) || is_array($value) || is_resource($value)) {
                throw new EncryptException(sprintf('Cannot encrypt a non-scalar value at %s:%s.', $entity::class, $field));
            }

            if ($encrypt) {
                if (!array_key_exists($field, $changeSet)) {
                    continue;
                }

                if (null !== $value) {
                    $property->setValue($entity, $this->encryptor->encrypt((string) $value, $field));
                    $this->rawValues[$objectId][$field] = $value;
                }

                $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
                continue;
            }

            if (null === $value) {
                continue;
            }

            $decryptedValue = $this->decryptValue((string) $value, $field);
            $property->setValue($entity, $decryptedValue);
            $unitOfWork->setOriginalEntityProperty($objectId, $field, $decryptedValue);
        }

        if ($encrypt && $blindIndexesUpdated) {
            $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
        }

        return true;
    }

    /** @return array<string, EncryptedField> */
    protected function getEncryptedFields(EntityManagerInterface $objectManager, object $entity): array
    {
        $metadata = $objectManager->getClassMetadata($entity::class);
        $className = $metadata->getName();

        return $this->encryptedFieldCache[$className]
            ??= $this->encryptedFieldMetadataProvider->getForClassMetadata($metadata);
    }

    /** @param LifecycleEventArgs<EntityManagerInterface> $args */
    private function restoreRawValues(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $objectId = spl_object_id($entity);

        if (!isset($this->rawValues[$objectId])) {
            return;
        }

        $metadata = $args->getObjectManager()->getClassMetadata($entity::class);
        $properties = $this->encryptedFieldMetadataProvider->getForClassMetadata($metadata);
        foreach ($this->rawValues[$objectId] as $field => $rawValue) {
            $properties[$field]->setValue($entity, $rawValue);
        }

        unset($this->rawValues[$objectId]);
    }
}
