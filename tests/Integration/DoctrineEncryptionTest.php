<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Kyzegs\DoctrineEncryptionBundle\Command\EncryptDatabaseCommand;
use Kyzegs\DoctrineEncryptionBundle\Tests\Integration\Fixture\EncryptedContact;
use Kyzegs\DoctrineEncryptionBundle\Tests\Integration\Fixture\EncryptedRecord;
use Kyzegs\DoctrineEncryptionBundle\Twig\EncryptExtension;
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

    public function testJsonArraysStayPlaintextInEntityAndEncryptedInDatabase(): void
    {
        $record = new EncryptedRecord();
        $record->secret = 'Secret';
        $record->mappedSecret = 'Mapped Secret';
        $record->contact = new EncryptedContact('Embedded Secret');
        $record->metadata = ['ticket' => 42, 'labels' => ['private', 'urgent']];
        $record->mappedMetadata = ['provider' => ['id' => 'abc']];
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        self::assertSame(['ticket' => 42, 'labels' => ['private', 'urgent']], $record->metadata);
        self::assertSame(['provider' => ['id' => 'abc']], $record->mappedMetadata);

        $raw = $this->entityManager->getConnection()->fetchAssociative('SELECT metadata, mapped_metadata FROM encrypted_record WHERE id = ?', [$record->id]);
        self::assertIsArray($raw);
        self::assertIsString($raw['metadata']);
        self::assertIsString($raw['mapped_metadata']);
        $metadataWrapper = json_decode($raw['metadata'], true, 512, \JSON_THROW_ON_ERROR);
        $mappedWrapper = json_decode($raw['mapped_metadata'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $metadataWrapper['__doctrine_encrypted']['version']);
        self::assertStringEndsWith('<ENC>', $metadataWrapper['__doctrine_encrypted']['ciphertext']);
        self::assertSame(1, $mappedWrapper['__doctrine_encrypted']['version']);

        $id = $record->id;
        $this->entityManager->clear();
        $loaded = $this->entityManager->find(EncryptedRecord::class, $id);
        self::assertInstanceOf(EncryptedRecord::class, $loaded);
        self::assertSame(['ticket' => 42, 'labels' => ['private', 'urgent']], $loaded->metadata);
        self::assertSame(['provider' => ['id' => 'abc']], $loaded->mappedMetadata);

        $loaded->metadata = ['updated' => true];
        $this->entityManager->flush();
        self::assertSame(['updated' => true], $loaded->metadata);
    }

    public function testPlaintextJsonRemainsReadableAndEncryptsAfterChange(): void
    {
        $record = new EncryptedRecord();
        $record->secret = 'Secret';
        $record->mappedSecret = 'Mapped Secret';
        $record->contact = new EncryptedContact('Embedded Secret');
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        $this->entityManager->getConnection()->update('encrypted_record', ['metadata' => json_encode(['legacy' => true], \JSON_THROW_ON_ERROR)], ['id' => $record->id]);
        $id = $record->id;
        $this->entityManager->clear();

        $loaded = $this->entityManager->find(EncryptedRecord::class, $id);
        self::assertInstanceOf(EncryptedRecord::class, $loaded);
        self::assertSame(['legacy' => true], $loaded->metadata);

        $loaded->secret = 'Changed Secret';
        $this->entityManager->flush();
        self::assertSame(json_encode(['legacy' => true], \JSON_THROW_ON_ERROR), $this->entityManager->getConnection()->fetchOne('SELECT metadata FROM encrypted_record WHERE id = ?', [$id]));

        $loaded->metadata = ['legacy' => false];
        $this->entityManager->flush();
        $raw = $this->entityManager->getConnection()->fetchOne('SELECT metadata FROM encrypted_record WHERE id = ?', [$id]);
        self::assertIsString($raw);
        self::assertArrayHasKey('__doctrine_encrypted', json_decode($raw, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testDatabaseCommandTransformsJsonArrays(): void
    {
        $record = new EncryptedRecord();
        $record->secret = 'Secret';
        $record->mappedSecret = 'Mapped Secret';
        $record->contact = new EncryptedContact('Embedded Secret');
        $record->metadata = ['rotate' => ['me']];
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        $command = self::getContainer()->get(EncryptDatabaseCommand::class);
        self::assertInstanceOf(EncryptDatabaseCommand::class, $command);
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute(['direction' => 'decrypt', '--force' => true]));
        $plaintext = $this->entityManager->getConnection()->fetchOne('SELECT metadata FROM encrypted_record');
        self::assertIsString($plaintext);
        self::assertSame(['rotate' => ['me']], json_decode($plaintext, true, 512, \JSON_THROW_ON_ERROR));

        self::assertSame(Command::SUCCESS, $tester->execute(['direction' => 'encrypt', '--force' => true]));
        $encrypted = $this->entityManager->getConnection()->fetchOne('SELECT metadata FROM encrypted_record');
        self::assertIsString($encrypted);
        self::assertArrayHasKey('__doctrine_encrypted', json_decode($encrypted, true, 512, \JSON_THROW_ON_ERROR));

        self::assertSame(Command::SUCCESS, $tester->execute(['direction' => 'rotate', '--force' => true]));
        $rotated = $this->entityManager->getConnection()->fetchOne('SELECT metadata FROM encrypted_record');
        self::assertIsString($rotated);
        self::assertNotSame($encrypted, $rotated);

        self::assertSame(Command::SUCCESS, $tester->execute(['direction' => 'decrypt', '--force' => true]));
        $decrypted = $this->entityManager->getConnection()->fetchOne('SELECT metadata FROM encrypted_record');
        self::assertIsString($decrypted);
        self::assertSame(['rotate' => ['me']], json_decode($decrypted, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testOptionalTwigExtensionIsRegistered(): void
    {
        $extension = self::getContainer()->get(EncryptExtension::class);

        self::assertInstanceOf(EncryptExtension::class, $extension);
        self::assertSame('plain text', $extension->decryptFilter('plain text'));
    }
}
