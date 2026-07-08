<?php

declare(strict_types=1);

namespace Yaup\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yaup\Config\ConfigLoader;
use Yaup\Repository\Registry;
use Yaup\Repository\RepositoryDiscoverer;

#[AsCommand(name: 'discover', description: 'Discover local projects and register remote-backed repositories')]
final class DiscoverCommand extends Command
{
    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $loader = new ConfigLoader();
        $config = $loader->load($this->root . '/config/yaup.yaml');
        $projectsDirectory = $config['projects_directory'] ?? 'repos';
        $registryFile = $config['registry_file'] ?? 'config/repositories.yaml';
        if (!is_string($projectsDirectory) || !is_string($registryFile)) {
            $io->error('projects_directory and registry_file must be strings.');
            return Command::INVALID;
        }
        $projects = $this->root . '/' . $projectsDirectory;
        $repositories = (new RepositoryDiscoverer())->discover($projects);
        $added = (new Registry($loader))->synchronize($this->root . '/' . $registryFile, $repositories);
        $io->table(['Project', 'Remote', 'Cross-repository CI'], array_map(
            static fn($repo): array => [$repo->name, $repo->remote ?? '-', null === $repo->remote ? 'excluded' : 'registered'],
            $repositories,
        ));
        $io->success(sprintf('Discovered %d projects; added %d registry entries.', count($repositories), $added));

        return Command::SUCCESS;
    }
}
