<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use SpecShaper\EncryptBundle\BlindIndex\BlindIndexField;
use SpecShaper\EncryptBundle\BlindIndex\BlindIndexMetadataProvider;
use SpecShaper\EncryptBundle\BlindIndex\BlindIndexUpdater;
use SpecShaper\EncryptBundle\Exception\EncryptException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'encrypt:blind-index', description: 'Builds or rebuilds blind-index columns')]
final class BlindIndexDatabaseCommand extends Command
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly BlindIndexMetadataProvider $blindIndexMetadataProvider,
        private readonly BlindIndexUpdater $blindIndexUpdater,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('manager', null, InputOption::VALUE_REQUIRED, 'Doctrine ORM manager name.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Entities per flush.', '250')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count entities without writing changes.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip the confirmation prompt.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
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

        $fieldsByClass = $this->blindIndexMetadataProvider->getAllForObjectManager($entityManager);
        $dryRun = (bool) $input->getOption('dry-run');
        $io->title('Building blind indexes'.($dryRun ? ' (dry run)' : ''));

        if (!$dryRun && !$input->getOption('force') && $input->isInteractive() && !$io->confirm('Rebuild all configured blind indexes?', false)) {
            $io->warning('No changes were made.');

            return Command::SUCCESS;
        }

        $processed = 0;
        foreach ($fieldsByClass as $className => $fields) {
            $processed += $this->updateEntities($entityManager, $className, $fields, $batchSize, $dryRun);
        }

        if (!$dryRun) {
            $entityManager->flush();
            $entityManager->clear();
        }

        $io->success(sprintf('%d entity/entities processed%s.', $processed, $dryRun ? '; no changes written' : ''));

        return Command::SUCCESS;
    }

    /**
     * @param class-string                   $className
     * @param array<string, BlindIndexField> $fields
     */
    private function updateEntities(EntityManagerInterface $entityManager, string $className, array $fields, int $batchSize, bool $dryRun): int
    {
        $query = $entityManager->createQueryBuilder()->select('entity')->from($className, 'entity')->getQuery();
        $processed = 0;

        foreach ($query->toIterable() as $entity) {
            if (!$dryRun) {
                $this->blindIndexUpdater->update($entity, $fields);
            }
            ++$processed;

            if (!$dryRun && 0 === $processed % $batchSize) {
                $entityManager->flush();
                $entityManager->clear();
            }
        }

        return $processed;
    }
}
