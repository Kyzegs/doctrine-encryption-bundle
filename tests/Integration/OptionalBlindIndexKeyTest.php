<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Tests\Integration\Fixture\EncryptedContact;
use Kyzegs\DoctrineEncryptionBundle\Tests\Integration\Fixture\EncryptedRecord;
use Kyzegs\DoctrineEncryptionBundle\Tests\Integration\Fixture\SimpleSecret;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A blind index key is only needed by applications that map a blind index.
 */
final class OptionalBlindIndexKeyTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $registry = $kernel->getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $entityManager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(SimpleSecret::class),
            $entityManager->getClassMetadata(EncryptedRecord::class),
        ]);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
        restore_exception_handler();
    }

    /** @param array{environment?: string, debug?: bool} $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new IntegrationKernel($options['environment'] ?? 'test', false, null);
    }

    public function testEncryptionWorksWithoutABlindIndexKey(): void
    {
        $record = new SimpleSecret();
        $record->secret = 'No Blind Index Here';
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        $raw = $this->entityManager->getConnection()->fetchOne('SELECT secret FROM simple_secret WHERE id = ?', [$record->id]);
        self::assertIsString($raw);
        self::assertStringStartsWith('SSEB1:gcm:integration:', $raw);

        $id = $record->id;
        $this->entityManager->clear();
        $loaded = $this->entityManager->find(SimpleSecret::class, $id);
        self::assertInstanceOf(SimpleSecret::class, $loaded);
        self::assertSame('No Blind Index Here', $loaded->secret);
    }

    public function testMappingABlindIndexWithoutAKeyFailsWithAClearMessage(): void
    {
        $record = new EncryptedRecord();
        $record->secret = 'Needs A Blind Index';
        $record->mappedSecret = 'Mapped Secret';
        $record->contact = new EncryptedContact('Embedded Secret');
        $this->entityManager->persist($record);

        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('Blind indexes require a "blind_index_key"');
        $this->entityManager->flush();
    }
}
