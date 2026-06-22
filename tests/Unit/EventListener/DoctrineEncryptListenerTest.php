<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Unit\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Kyzegs\DoctrineEncryptionBundle\Annotations\Encrypted;
use Kyzegs\DoctrineEncryptionBundle\BlindIndex\BlindIndexMetadataProvider;
use Kyzegs\DoctrineEncryptionBundle\BlindIndex\BlindIndexUpdater;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface;
use Kyzegs\DoctrineEncryptionBundle\EventListener\DoctrineEncryptListener;
use Kyzegs\DoctrineEncryptionBundle\Hashers\BlindIndexHasherInterface;
use Kyzegs\DoctrineEncryptionBundle\Mapping\EncryptedFieldMetadataProvider;
use PHPUnit\Framework\TestCase;

class DoctrineEncryptListenerTest extends TestCase
{
    public function testEncryptsChangedEmbeddedField(): void
    {
        $entity = new EntityWithEncryptedEmbeddable(new EncryptedAddress('plain-secret'));
        $meta = $this->createEntityMetadata();

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')->willReturn([
            'address.secret' => ['old-secret', 'plain-secret'],
        ]);
        $unitOfWork
            ->expects($this->once())
            ->method('recomputeSingleEntityChangeSet')
            ->with($meta, $entity);

        $objectManager = $this->createObjectManager($meta, $unitOfWork);
        $encryptor = new RecordingEncryptor();
        $listener = $this->createListener($encryptor);

        self::assertTrue($listener->process($objectManager, $entity, true));
        self::assertSame('encrypted[address.secret]:plain-secret', $entity->address->secret);
        self::assertSame([['plain-secret', 'address.secret']], $encryptor->encryptCalls);
    }

    public function testDecryptsEmbeddedFieldAndUpdatesOriginalEntityProperty(): void
    {
        $entity = new EntityWithEncryptedEmbeddable(new EncryptedAddress('encrypted[address.secret]:plain-secret'));
        $meta = $this->createEntityMetadata();

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects($this->once())
            ->method('setOriginalEntityProperty')
            ->with(spl_object_id($entity), 'address.secret', 'plain-secret');

        $objectManager = $this->createObjectManager($meta, $unitOfWork);
        $encryptor = new RecordingEncryptor();
        $listener = $this->createListener($encryptor);

        self::assertTrue($listener->process($objectManager, $entity, false));
        self::assertSame('plain-secret', $entity->address->secret);
        self::assertSame([['encrypted[address.secret]:plain-secret', 'address.secret']], $encryptor->decryptCalls);
    }

    /** @param ClassMetadata<object> $meta */
    private function createObjectManager(ClassMetadata $meta, UnitOfWork $unitOfWork): EntityManagerInterface
    {
        $objectManager = $this->createMock(EntityManagerInterface::class);
        $objectManager
            ->method('getClassMetadata')
            ->with(EntityWithEncryptedEmbeddable::class)
            ->willReturn($meta);
        $objectManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        return $objectManager;
    }

    private function createListener(EncryptorInterface $encryptor): TestableDoctrineEncryptListener
    {
        return new TestableDoctrineEncryptListener(
            $encryptor,
            false,
            new BlindIndexMetadataProvider(),
            new BlindIndexUpdater($this->createMock(BlindIndexHasherInterface::class)),
            new EncryptedFieldMetadataProvider([Encrypted::class])
        );
    }

    /** @return ClassMetadata<object> */
    private function createEntityMetadata(): ClassMetadata
    {
        $reflectionService = new RuntimeReflectionService();

        $addressMeta = new ClassMetadata(EncryptedAddress::class);
        $addressMeta->mapField([
            'fieldName' => 'secret',
            'type' => 'string',
            'columnName' => 'secret',
        ]);
        $addressMeta->wakeupReflection($reflectionService);

        $entityMeta = new ClassMetadata(EntityWithEncryptedEmbeddable::class);
        $entityMeta->mapEmbedded([
            'fieldName' => 'address',
            'class' => EncryptedAddress::class,
        ]);
        $entityMeta->wakeupReflection($reflectionService);
        $entityMeta->inlineEmbeddable('address', $addressMeta);
        $entityMeta->wakeupReflection($reflectionService);

        return $entityMeta;
    }
}

class TestableDoctrineEncryptListener extends DoctrineEncryptListener
{
    public function process(EntityManagerInterface $objectManager, object $entity, bool $isEncryptOperation): bool
    {
        return $this->processFields($objectManager, $entity, $isEncryptOperation);
    }
}

class RecordingEncryptor implements EncryptorInterface
{
    /** @var list<array{string|null, string|null}> */
    public array $encryptCalls = [];

    /** @var list<array{string|null, string|null}> */
    public array $decryptCalls = [];

    public function setSecretKey(string $key): void
    {
    }

    public function encrypt(?string $data, ?string $columnName = null): ?string
    {
        $this->encryptCalls[] = [$data, $columnName];

        return null === $data ? null : sprintf('encrypted[%s]:%s', $columnName, $data);
    }

    public function decrypt(?string $data, ?string $columnName = null): ?string
    {
        $this->decryptCalls[] = [$data, $columnName];

        return null === $data ? null : str_replace(sprintf('encrypted[%s]:', $columnName), '', $data);
    }
}

class EntityWithEncryptedEmbeddable
{
    public function __construct(public EncryptedAddress $address)
    {
    }
}

class EncryptedAddress
{
    public function __construct(
        #[Encrypted]
        public ?string $secret,
    ) {
    }
}
