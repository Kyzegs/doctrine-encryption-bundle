<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI command to generate a 256-bit encryption key.
 */
#[AsCommand(name: 'encrypt:genkey')]
class GenKeyCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Generate a 256-bit encryption key.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $encryption_key_256bit = base64_encode(random_bytes(32));

        $io = new SymfonyStyle($input, $output);
        $io->title('Generated Key');
        $io->success('Key is: '.$encryption_key_256bit);

        return Command::SUCCESS;
    }
}
