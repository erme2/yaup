<?php

declare(strict_types=1);

namespace Yaup\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yaup\Config\ConfigLoader;
use Yaup\Validation\ValidationRunner;

#[AsCommand(name: 'validate', description: 'Run explicitly configured project validation categories')]
final class ValidateCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('project', InputArgument::REQUIRED);
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $project = $input->getArgument('project');
        if (!is_string($project)) {
            return Command::INVALID;
        }
        $results = (new ValidationRunner(new ConfigLoader()))->run($project);
        $io->table(['Category', 'Status', 'Detail'], array_map(static fn(string $category, array $result): array => [$category, $result['status'], $result['detail']], array_keys($results), $results));
        foreach ($results as $result) {
            if ('failed' === $result['status']) {
                return Command::FAILURE;
            }
        }
        return Command::SUCCESS;
    }
}
