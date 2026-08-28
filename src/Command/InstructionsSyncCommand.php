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

#[AsCommand(name: 'instructions:sync', description: 'Create or refresh Yaup AGENTS.md bridge files in registered repositories')]
final class InstructionsSyncCommand extends Command
{
    private const MARKER = '<!-- yaup-managed-agent-bridge -->';

    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('project', InputArgument::IS_ARRAY, 'Optional registered project names to sync');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $loader = new ConfigLoader();
        $config = $loader->load($this->root . '/config/yaup.yaml');
        $registryFile = $config['registry_file'] ?? 'config/repositories.yaml';
        if (!is_string($registryFile) || '' === $registryFile) {
            $io->error('registry_file must be a non-empty string.');
            return Command::INVALID;
        }

        $registry = $loader->load($this->root . '/' . $registryFile);
        $repositories = $registry['repositories'] ?? [];
        if (!is_array($repositories)) {
            $io->error('repositories must be a list.');
            return Command::INVALID;
        }

        $projectArguments = $input->getArgument('project');
        if (!is_array($projectArguments)) {
            $io->error('project filters must be strings.');
            return Command::INVALID;
        }
        $selectedProjects = [];
        foreach ($projectArguments as $project) {
            if (!is_string($project)) {
                $io->error('project filters must be strings.');
                return Command::INVALID;
            }
            $selectedProjects[] = $project;
        }

        $rows = [];
        foreach ($repositories as $repository) {
            if (!is_array($repository) || !isset($repository['name'], $repository['path']) || !is_string($repository['name']) || !is_string($repository['path'])) {
                continue;
            }
            if ([] !== $selectedProjects && !in_array($repository['name'], $selectedProjects, true)) {
                continue;
            }

            $status = $this->syncRepository($repository['name'], $repository['path']);
            $rows[] = [$repository['name'], $repository['path'], $status];
        }

        $syncedNames = array_map(static fn(array $row): string => $row[0], $rows);
        $unknownProjects = array_values(array_diff($selectedProjects, $syncedNames));
        if ([] !== $unknownProjects) {
            $io->error('Unknown registered project: ' . implode(', ', $unknownProjects));
            return Command::FAILURE;
        }

        $io->table(['Project', 'Path', 'AGENTS.md'], $rows);
        $io->success('Synchronized Yaup agent bridge files.');

        return Command::SUCCESS;
    }

    private function syncRepository(string $name, string $path): string
    {
        if (!is_dir($path)) {
            return 'missing checkout';
        }

        $file = rtrim($path, '/') . '/AGENTS.md';
        $content = $this->bridgeContent($name);
        if (!is_file($file)) {
            file_put_contents($file, $content);

            return 'created';
        }

        $existing = file_get_contents($file);
        if (!is_string($existing) || !$this->isManagedBridge($existing)) {
            return 'preserved';
        }

        if ($existing === $content) {
            return 'current';
        }

        file_put_contents($file, $content);

        return 'updated';
    }

    private function bridgeContent(string $name): string
    {
        return self::MARKER . "\n"
            . "# Agent Instructions\n\n"
            . "This repository is registered with Yaup as `{$name}` and lives inside `{$this->root}/repos`.\n"
            . "Yaup is the authoritative source for shared agent rules, guardrails, playbooks, and skills.\n\n"
            . "Before doing ticket work here, read and follow:\n\n"
            . "- `{$this->root}/policies/core.md`\n"
            . "- `{$this->root}/policies/rules.yaml`\n"
            . "- the relevant skill in `{$this->root}/skills/`\n"
            . "- the relevant playbook in `{$this->root}/playbooks/`\n\n"
            . "Repository-local instructions may add project-specific context, but they must not weaken or override Yaup rules.\n"
            . "If instructions conflict, Yaup wins.\n";
    }

    private function isManagedBridge(string $content): bool
    {
        return str_contains($content, self::MARKER)
            || str_contains($content, "This repository is inside `{$this->root}/repos`")
            && str_contains($content, 'Yaup is the')
            && str_contains($content, 'authoritative source for agent rules, guardrails, playbooks, and skills.');
    }
}
