<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptedJsonCodec;
use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Mapping\EncryptedFieldMetadataProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'encrypt:database', description: 'Encrypts, decrypts, or rotates encrypted database columns')]
final class EncryptDatabaseCommand extends Command
{
    private readonly EncryptedJsonCodec $encryptedJsonCodec;

    public function __construct(
        private readonly EncryptorInterface $encryptor,
        private readonly ManagerRegistry $registry,
        private readonly EncryptedFieldMetadataProvider $encryptedFieldMetadataProvider,
        ?EncryptedJsonCodec $encryptedJsonCodec = null,
    ) {
        $this->encryptedJsonCodec = $encryptedJsonCodec ?? new EncryptedJsonCodec($encryptor);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('direction', InputArgument::REQUIRED, 'One of: encrypt, decrypt, rotate.')
            ->addOption('manager', null, InputOption::VALUE_REQUIRED, 'Doctrine ORM manager name.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows per transaction.', '250')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Inspect and count rows without writing changes.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip the confirmation prompt.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $operationName = (string) $input->getArgument('direction');
        $operation = DatabaseOperation::tryFrom($operationName);
        if (null === $operation) {
            $io->error(sprintf('Invalid direction "%s". Choose encrypt, decrypt, or rotate.', $operationName));

            return Command::INVALID;
        }

        $batchSize = filter_var($input->getOption('batch-size'), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $batchSize) {
            $io->error('--batch-size must be a positive integer.');

            return Command::INVALID;
        }

        $managerName = $input->getOption('manager') ?: $this->registry->getDefaultManagerName();
        $entityManager = $this->registry->getManager($managerName);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new EncryptException(sprintf('Manager "%s" is not a Doctrine ORM entity manager.', $managerName));
        }

        $tables = $this->collectTables($entityManager);
        if ([] === $tables) {
            $io->success('No encrypted fields were found.');

            return Command::SUCCESS;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $io->title(sprintf('%s encrypted database fields%s', ucfirst($operation->value), $dryRun ? ' (dry run)' : ''));
        $io->writeln(sprintf('%d table(s) will be processed.', count($tables)));

        if (!$dryRun && !$input->getOption('force') && $input->isInteractive() && !$io->confirm('Backups are strongly recommended. Continue?', false)) {
            $io->warning('No changes were made.');

            return Command::SUCCESS;
        }

        $processed = 0;
        foreach ($tables as $table) {
            $tableProcessed = $this->processTable($entityManager->getConnection(), $table, $operation, $batchSize, $dryRun);
            $processed += $tableProcessed;
            $io->writeln(sprintf('Processed %s (%d rows).', $table->name, $tableProcessed));
        }

        $io->success(sprintf('%d row(s) processed%s.', $processed, $dryRun ? '; no changes written' : ''));

        return Command::SUCCESS;
    }

    /** @return list<EncryptedDatabaseTable> */
    private function collectTables(EntityManagerInterface $entityManager): array
    {
        $tables = [];

        /** @var ClassMetadata<object> $metadata */
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass) {
                continue;
            }

            $encryptedFields = $this->encryptedFieldMetadataProvider->getForClassMetadata($metadata);
            if ([] === $encryptedFields) {
                continue;
            }

            $identifiers = [];
            foreach ($metadata->getIdentifierFieldNames() as $field) {
                if (!$metadata->hasField($field)) {
                    throw new EncryptException(sprintf('Entity "%s" uses an association identifier, which encrypt:database cannot safely update.', $metadata->getName()));
                }
                $identifiers[$field] = $metadata->getColumnName($field);
            }

            if ([] === $identifiers) {
                throw new EncryptException(sprintf('Entity "%s" has no mapped identifier.', $metadata->getName()));
            }

            foreach ($encryptedFields as $field => $encryptedField) {
                $mapping = $metadata->getFieldMapping($field);
                $declaringClass = $mapping['inherited'] ?? $mapping['declared'] ?? $metadata->getName();
                $tableMetadata = $entityManager->getClassMetadata($declaringClass);
                if ($tableMetadata->isMappedSuperclass) {
                    $tableMetadata = $metadata;
                }

                $tableName = $tableMetadata->getTableName();
                $key = $tableName."\0".implode(',', $identifiers);
                $tables[$key] ??= new EncryptedDatabaseTable($tableName, $identifiers);
                $tables[$key]->addField(new DatabaseEncryptedField(
                    field: $field,
                    column: $metadata->getColumnName($field),
                    format: $encryptedField->getFormat(),
                ));
            }
        }

        return array_values($tables);
    }

    private function processTable(Connection $connection, EncryptedDatabaseTable $table, DatabaseOperation $operation, int $batchSize, bool $dryRun): int
    {
        $platform = $connection->getDatabasePlatform();
        $quotedTable = $platform->quoteIdentifier($table->name);
        $selections = [];
        $fieldAliases = [];
        $identifierAliases = [];
        $ordering = [];

        foreach ($table->identifiers as $column) {
            $alias = '__identifier_'.count($identifierAliases);
            $identifierAliases[$alias] = $column;
            $selections[] = $platform->quoteIdentifier($column).' AS '.$platform->quoteIdentifier($alias);
            $ordering[] = $platform->quoteIdentifier($column);
        }
        foreach ($table->fields() as $fieldMapping) {
            $alias = '__encrypted_'.count($fieldAliases);
            $fieldAliases[$alias] = $fieldMapping;
            $selections[] = $platform->quoteIdentifier($fieldMapping->column).' AS '.$platform->quoteIdentifier($alias);
        }

        $baseQuery = sprintf('SELECT %s FROM %s ORDER BY %s', implode(', ', $selections), $quotedTable, implode(', ', $ordering));
        $offset = 0;
        $processed = 0;

        do {
            $query = $platform->modifyLimitQuery($baseQuery, $batchSize, $offset);
            $rows = $connection->fetchAllAssociative($query);
            if ([] === $rows) {
                break;
            }

            if (!$dryRun) {
                $connection->beginTransaction();
            }

            try {
                foreach ($rows as $row) {
                    if (!$dryRun) {
                        $this->updateRow($connection, $quotedTable, $row, $fieldAliases, $identifierAliases, $operation);
                    }
                    ++$processed;
                }
                if (!$dryRun) {
                    $connection->commit();
                }
            } catch (\Throwable $exception) {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                throw $exception;
            }

            $offset += count($rows);
        } while (count($rows) === $batchSize);

        return $processed;
    }

    /**
     * @param array<string, mixed>                  $row
     * @param array<string, DatabaseEncryptedField> $fieldAliases
     * @param array<string, string>                 $identifierAliases
     */
    private function updateRow(Connection $connection, string $quotedTable, array $row, array $fieldAliases, array $identifierAliases, DatabaseOperation $operation): void
    {
        $platform = $connection->getDatabasePlatform();
        $assignments = [];
        $conditions = [];
        $parameters = [];

        foreach ($fieldAliases as $alias => $mapping) {
            $value = $row[$alias];
            $newValue = Encrypted::FORMAT_JSON === $mapping->format
                ? $this->transformJsonValue($value, $mapping->field, $operation)
                : match ($operation) {
                    DatabaseOperation::ENCRYPT => $this->encryptor->encrypt($value, $mapping->field),
                    DatabaseOperation::DECRYPT => $this->encryptor->decrypt($value, $mapping->field),
                    DatabaseOperation::ROTATE => $this->encryptor->encrypt($this->encryptor->decrypt($value, $mapping->field), $mapping->field),
                };
            $parameter = 'field_'.count($parameters);
            $assignments[] = $platform->quoteIdentifier($mapping->column).' = :'.$parameter;
            $parameters[$parameter] = $newValue;
        }

        foreach ($identifierAliases as $alias => $column) {
            $parameter = 'identifier_'.count($parameters);
            $conditions[] = $platform->quoteIdentifier($column).' = :'.$parameter;
            $parameters[$parameter] = $row[$alias];
        }

        $connection->executeStatement(
            sprintf('UPDATE %s SET %s WHERE %s', $quotedTable, implode(', ', $assignments), implode(' AND ', $conditions)),
            $parameters,
        );
    }

    private function transformJsonValue(mixed $value, string $field, DatabaseOperation $operation): ?string
    {
        if (null === $value) {
            return null;
        }

        $context = sprintf('database field "%s"', $field);
        $decoded = $this->encryptedJsonCodec->decodeJson($value, $context);
        $transformed = match ($operation) {
            DatabaseOperation::ENCRYPT => $this->encryptedJsonCodec->encrypt($decoded, $field, $context),
            DatabaseOperation::DECRYPT => $this->encryptedJsonCodec->decrypt($decoded, $field, $context),
            DatabaseOperation::ROTATE => $this->encryptedJsonCodec->encrypt($this->encryptedJsonCodec->decrypt($decoded, $field, $context), $field, $context),
        };

        return $this->encryptedJsonCodec->encodeJson($transformed, $context);
    }
}
