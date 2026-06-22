<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use SpecShaper\EncryptBundle\Command\EncryptDatabaseCommand;
use SpecShaper\EncryptBundle\Tests\Integration\Fixture\EncryptedContact;
use SpecShaper\EncryptBundle\Tests\Integration\Fixture\EncryptedRecord;
use SpecShaper\EncryptBundle\Twig\EncryptExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class DoctrineEncryptionTest extends KernelTestCase
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
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(EncryptedRecord::class)]);
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

    public function testInsertHydrateAndUpdateKeepPlaintextInEntityAndCiphertextInDatabase(): void
    {
        $record = new EncryptedRecord();
        $record->secret = 'First Secret';
        $record->mappedSecret = 'Mapped Secret';
        $record->contact = new EncryptedContact('Embedded Secret');
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        self::assertSame('First Secret', $record->secret);
        self::assertSame(hash_hmac('sha256', 'first secret', 'a-distinct-blind-index-test-key'), $record->secretLookup);

        $raw = $this->entityManager->getConnection()->fetchAssociative('SELECT secret_value, mapped_secret, contact_token, secret_lookup FROM encrypted_record WHERE id = ?', [$record->id]);
        self::assertIsArray($raw);
        self::assertStringStartsWith('SSEB1:gcm:integration:', $raw['secret_value']);
        self::assertStringEndsWith('<ENC>', $raw['secret_value']);
        self::assertStringStartsWith('SSEB1:gcm:integration:', $raw['mapped_secret']);
        self::assertStringStartsWith('SSEB1:gcm:integration:', $raw['contact_token']);
        self::assertSame($record->secretLookup, $raw['secret_lookup']);

        $id = $record->id;
        $this->entityManager->clear();
        $loaded = $this->entityManager->find(EncryptedRecord::class, $id);
        self::assertInstanceOf(EncryptedRecord::class, $loaded);
        self::assertSame('First Secret', $loaded->secret);
        self::assertSame('Mapped Secret', $loaded->mappedSecret);
        self::assertSame('Embedded Secret', $loaded->contact->token);

        $loaded->secret = 'Second Secret';
        $this->entityManager->flush();
        self::assertSame('Second Secret', $loaded->secret);

        $updated = $this->entityManager->getConnection()->fetchOne('SELECT secret_value FROM encrypted_record WHERE id = ?', [$id]);
        self::assertIsString($updated);
        self::assertNotSame($raw['secret_value'], $updated);
    }

    public function testDatabaseCommandDecryptsAndEncryptsInBatches(): void
    {
        $record = new EncryptedRecord();
        $record->secret = 'Command Secret';
        $record->mappedSecret = 'Mapped Command Secret';
        $record->contact = new EncryptedContact('Embedded Command Secret');
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        $command = self::getContainer()->get(EncryptDatabaseCommand::class);
        self::assertInstanceOf(EncryptDatabaseCommand::class, $command);
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute([
            'direction' => 'decrypt',
            '--force' => true,
            '--batch-size' => 1,
        ]));
        self::assertSame('Command Secret', $this->entityManager->getConnection()->fetchOne('SELECT secret_value FROM encrypted_record'));
        self::assertSame('Embedded Command Secret', $this->entityManager->getConnection()->fetchOne('SELECT contact_token FROM encrypted_record'));

        self::assertSame(Command::SUCCESS, $tester->execute([
            'direction' => 'encrypt',
            '--force' => true,
            '--batch-size' => 1,
        ]));
        $encrypted = $this->entityManager->getConnection()->fetchOne('SELECT secret_value FROM encrypted_record');
        self::assertIsString($encrypted);
        self::assertStringStartsWith('SSEB1:gcm:integration:', $encrypted);
        $embeddedEncrypted = $this->entityManager->getConnection()->fetchOne('SELECT contact_token FROM encrypted_record');
        self::assertIsString($embeddedEncrypted);
        self::assertStringStartsWith('SSEB1:gcm:integration:', $embeddedEncrypted);
    }

    public function testOptionalTwigExtensionIsRegistered(): void
    {
        $extension = self::getContainer()->get(EncryptExtension::class);

        self::assertInstanceOf(EncryptExtension::class, $extension);
        self::assertSame('plain text', $extension->decryptFilter('plain text'));
    }
}
