<?php

namespace SpecShaper\EncryptBundle\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use ReflectionProperty;
use SpecShaper\EncryptBundle\BlindIndex\BlindIndexMetadataProvider;
use SpecShaper\EncryptBundle\BlindIndex\BlindIndexUpdater;
use SpecShaper\EncryptBundle\Encryptors\EncryptorInterface;
use SpecShaper\EncryptBundle\Exception\EncryptException;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use SpecShaper\EncryptBundle\Mapping\EncryptedFieldMetadataProvider;

/**
 * Doctrine event listener which encrypts/decrypts entities.
 */
#[AsDoctrineListener(event: Events::postLoad, priority: 500)]
#[AsDoctrineListener(event: Events::postUpdate, priority: 500)]
#[AsDoctrineListener(event: Events::onFlush, priority: 500)]
class DoctrineEncryptListener implements DoctrineEncryptListenerInterface
{
    /**
     * Encryptor interface namespace.
     */
    public const ENCRYPTOR_INTERFACE_NS = EncryptorInterface::class;

    /**
     * Caches information on an entity's encrypted fields in an array keyed on
     * the entity's class name. The value will be a list of Reflected fields that are encrypted.
     */
    protected array $encryptedFieldCache = [];

    private array $rawValues = [];

    public function __construct(
        private readonly EncryptorInterface $encryptor,
        private bool $isDisabled,
        private readonly BlindIndexMetadataProvider $blindIndexMetadataProvider,
        private readonly BlindIndexUpdater $blindIndexUpdater,
        private readonly EncryptedFieldMetadataProvider $encryptedFieldMetadataProvider
    ) {
    }

    public function getEncryptor(): EncryptorInterface
    {
        return $this->encryptor;
    }

    /**
     * Set Is Disabled.
     *
     * Used to programmatically disable encryption on flush operations.
     * Decryption still occurs if values have the <ENC> suffix.
     */
    public function setIsDisabled(?bool $isDisabled = true): DoctrineEncryptListenerInterface
    {
        $this->isDisabled = $isDisabled;

        return $this;
    }

    /**
     * @throws EncryptException
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->isDisabled) {
            return;
        }

        $objectManager = $args->getObjectManager();
        $unitOfWork = $objectManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->processFields($objectManager, $entity, true, true);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->processFields($objectManager, $entity, true, false);
        }
    }

    /**
     * Listen a postLoad lifecycle event. Checking and decrypt entities
     * which have @Encrypted annotations.
     *
     * @throws EncryptException
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        // Decrypt the entity fields.
        $this->processFields($args->getObjectManager(), $entity, false, false);
    }

    /**
     * Decrypt a value.
     *
     * If the value is an object, or if it does not contain the suffic <ENC> then return the value iteslf back.
     * Otherwise, decrypt the value and return.
     */
    public function decryptValue(?string $value, ?string $columnName): ?string
    {
        // Else decrypt value and return.
        return $this->encryptor->decrypt($value, $columnName);
    }

    public function getEncryptionableProperties(array $allProperties): array
    {
        $encryptedFields = [];

        foreach ($allProperties as $refProperty) {
            if ($this->isEncryptedProperty($refProperty)) {
                $encryptedFields[] = $refProperty;
            }
        }

        return $encryptedFields;
    }

    /**
     * Process (encrypt/decrypt) entities fields.
     */
    protected function processFields(ObjectManager $objectManager, object $entity, bool $isEncryptOperation, bool $isInsert): bool
    {
        // Get the encrypted properties in the entity.
        $properties = $this->getEncryptedFields($objectManager, $entity);
        $blindIndexes = $this->blindIndexMetadataProvider->getForEntity($objectManager, $entity);

        // If no encrypted properties, return false.
        if (empty($properties) && empty($blindIndexes)) {
            return false;
        }

        $unitOfWork = $objectManager->getUnitOfWork();
        $oid = spl_object_id($entity);
        $meta = $objectManager->getClassMetadata(get_class($entity));
        $changeSet = $unitOfWork->getEntityChangeSet($entity);
        $blindIndexesUpdated = false;

        if ($isEncryptOperation && !empty($blindIndexes)) {
            $blindIndexesUpdated = $this->blindIndexUpdater->update($entity, $blindIndexes, $changeSet);
        }

        foreach ($properties as $refProperty) {

            $field = $refProperty->getName();

            // Get the value in the entity.
            $value = $refProperty->getValue($entity);

            if (is_object($value)) {
                throw new EncryptException('Cannot encrypt an object at '.$refProperty->class.':'.$refProperty->getName(), $value);
            }

            // Encryption is fired by onFlush event, else it is an onLoad event.
            if ($isEncryptOperation) {
                // Encrypt value only if change has been detected by Doctrine (comparing unencrypted values, see postLoad flow)
                if (isset($changeSet[$field])) {
                    if (null !== $value) {
                        $encryptedValue = $this->encryptor->encrypt($value, $field);
                        $refProperty->setValue($entity, $encryptedValue);

                        // Will be restored during postUpdate cycle for updates, or below for inserts
                        $this->rawValues[$oid][$field] = $value;
                    }

                    $unitOfWork->recomputeSingleEntityChangeSet($meta, $entity);
                }
            } else {
                // Skip any null values.
                if (null === $value) {
                    continue;
                }

                // Decryption is fired by onLoad and postFlush events.
                $decryptedValue = $this->decryptValue($value, $field);
                $refProperty->setValue($entity, $decryptedValue);

                // Tell Doctrine the original value was the decrypted one.
                $unitOfWork->setOriginalEntityProperty($oid, $field, $decryptedValue);
            }
        }

        if ($isEncryptOperation && $blindIndexesUpdated) {
            $unitOfWork->recomputeSingleEntityChangeSet($meta, $entity);
        }

        if ($isInsert && isset($this->rawValues[$oid])) {
            // Restore the decrypted values after the change set update
            foreach ($this->rawValues[$oid] as $prop => $rawValue) {
                $refProperty = $meta->getReflectionProperty($prop);
                $refProperty->setValue($entity, $rawValue);
            }

            unset($this->rawValues[$oid]);
        }

        return true;
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $oid = spl_object_id($entity);

        $objectManager = $args->getObjectManager();

        if (isset($this->rawValues[$oid])) {
            $className = get_class($entity);
            $meta = $objectManager->getClassMetadata($className);
            foreach ($this->rawValues[$oid] as $prop => $rawValue) {
                $refProperty = $meta->getReflectionProperty($prop);
                $refProperty->setValue($entity, $rawValue);
            }

            unset($this->rawValues[$oid]);
        }
    }

    /**
     * @return array<string, ReflectionProperty>
     */
    protected function getEncryptedFields(ObjectManager $objectManager, object $entity): array
    {
        $classMetadata = $objectManager->getClassMetadata(get_class($entity));
        $className = $classMetadata->getName();

        if (isset($this->encryptedFieldCache[$className])) {
            return $this->encryptedFieldCache[$className];
        }

        $encryptedFields = $this->encryptedFieldMetadataProvider->getForClassMetadata($classMetadata);

        $this->encryptedFieldCache[$className] = $encryptedFields;

        return $encryptedFields;
    }

    private function isEncryptedProperty(ReflectionProperty $refProperty): bool
    {
        return $this->encryptedFieldMetadataProvider->hasEncryptedAttribute($refProperty);
    }

    protected function getOriginalEntityReflection(ObjectManager $objectManager, $entity): \ReflectionClass
    {
        $realClassName = $objectManager->getClassMetadata(get_class($entity))->getName();
        return new \ReflectionClass($realClassName);
    }
}
