<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Kyzegs\DoctrineEncryptionBundle\Tests\Integration\Fixture\TypedSecret;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class EncryptedTextTypeTest extends KernelTestCase
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
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(TypedSecret::class)]);
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
        return new IntegrationKernel($options['environment'] ?? 'test', false);
    }

    public function testTypedColumnStoresCiphertextAndHydratesPlaintext(): void
    {
        $record = new TypedSecret();
        $record->secret = 'Typed Secret';
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        $raw = $this->entityManager->getConnection()->fetchAssociative('SELECT secret, optional_secret FROM typed_secret WHERE id = ?', [$record->id]);
        self::assertIsArray($raw);
        self::assertIsString($raw['secret']);
        self::assertStringStartsWith('SSEB1:gcm:integration:', $raw['secret']);
        self::assertStringEndsWith('<ENC>', $raw['secret']);
        self::assertNull($raw['optional_secret']);

        $id = $record->id;
        $this->entityManager->clear();

        $loaded = $this->entityManager->find(TypedSecret::class, $id);
        self::assertInstanceOf(TypedSecret::class, $loaded);
        self::assertSame('Typed Secret', $loaded->secret);
        self::assertNull($loaded->optionalSecret);
    }

    /**
     * Array and partial hydration read the column without ever constructing a managed entity, so the
     * lifecycle listener never sees them. The type converts them anyway.
     */
    public function testArrayAndPartialHydrationAreDecrypted(): void
    {
        $record = new TypedSecret();
        $record->secret = 'Hydrated Secret';
        $this->entityManager->persist($record);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $entityRows = $this->entityManager
            ->createQuery('SELECT t FROM '.TypedSecret::class.' t')
            ->getArrayResult();
        self::assertSame('Hydrated Secret', $entityRows[0]['secret']);

        $fieldRows = $this->entityManager
            ->createQuery('SELECT t.id, t.secret FROM '.TypedSecret::class.' t')
            ->getArrayResult();
        self::assertSame('Hydrated Secret', $fieldRows[0]['secret']);

        $partial = $this->entityManager
            ->createQuery('SELECT PARTIAL t.{id, secret} FROM '.TypedSecret::class.' t')
            ->getSingleResult();
        self::assertInstanceOf(TypedSecret::class, $partial);
        self::assertSame('Hydrated Secret', $partial->secret);
    }

    /**
     * Pins ORM's documented exception: gatherScalarRowData() deliberately skips conversion for a scalar
     * mapping, so getScalarResult() and getSingleScalarResult() hand back the raw column.
     */
    public function testScalarResultIsNotConvertedByTheOrm(): void
    {
        $record = new TypedSecret();
        $record->secret = 'Scalar Secret';
        $this->entityManager->persist($record);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $scalar = $this->entityManager
            ->createQuery('SELECT t.secret FROM '.TypedSecret::class.' t')
            ->getSingleScalarResult();

        self::assertIsString($scalar);
        self::assertStringStartsWith('SSEB1:gcm:', $scalar);
    }

    public function testQueryingByPlaintextFindsNothing(): void
    {
        $record = new TypedSecret();
        $record->secret = 'Not Searchable';
        $this->entityManager->persist($record);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Randomized encryption means the parameter is encrypted to a different ciphertext every time.
        $found = $this->entityManager->getRepository(TypedSecret::class)->findOneBy(['secret' => 'Not Searchable']);
        self::assertNull($found);
    }
}
