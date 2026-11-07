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
use Yaup\Repository\Registry;

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
        try {
            // Name-based commands retain the last registration for each name.
            $registered = array_column((new Registry($loader))->registeredPaths($this->root), null, 'name');
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

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

        $unknownProjects = array_values(array_diff($selectedProjects, array_column($registered, 'name')));
        if ([] !== $unknownProjects) {
            $io->error('Unknown registered project: ' . implode(', ', $unknownProjects));
            return Command::FAILURE;
        }

        $rows = [];
        $failed = false;
        foreach ($registered as $repository) {
            $name = $repository['name'];
            $path = $repository['path'];
            if ([] !== $selectedProjects && !in_array($name, $selectedProjects, true)) {
                continue;
            }

            $status = $this->syncRepository($name, $path);
            if ('write failed' === $status) {
                $failed = true;
            }

            $rows[] = [$name, $path, $status];
        }

        $io->table(['Project', 'Path', 'AGENTS.md'], $rows);
        if ($failed) {
            $io->error('Failed to write one or more Yaup agent bridge files.');

            return Command::FAILURE;
        }

        $io->success('Synchronized Yaup agent bridge files.');

        return Command::SUCCESS;
    }

    private function syncRepository(string $name, string $path): string
    {
        if (!is_dir($path)) {
            return 'missing checkout';
        }

        $file = rtrim($path, '/') . '/AGENTS.md';
        $content = $this->bridgeContent($name, $path);
        if (!is_file($file)) {
            if (!$this->writeFile($file, $content)) {
                return 'write failed';
            }

            return 'created';
        }

        $existing = file_get_contents($file);
        if (!is_string($existing) || !$this->isManagedBridge($existing)) {
            return 'preserved';
        }

        if ($existing === $content) {
            return 'current';
        }

        if (!$this->writeFile($file, $content)) {
            return 'write failed';
        }

        return 'updated';
    }

    private function writeFile(string $file, string $content): bool
    {
        return false !== @file_put_contents($file, $content);
    }

    private function bridgeContent(string $name, string $path): string
    {
        return self::MARKER . "\n"
            . "# Agent Instructions\n\n"
            . "This repository is registered with Yaup as `{$name}` and {$this->locationSentence($name, $path)}\n"
            . "Yaup is the authoritative source for shared agent rules, guardrails, playbooks, and skills.\n\n"
            . "Before doing ticket work here, read and follow (paths relative to the Yaup root):\n\n"
            . "- `policies/core.md`\n"
            . "- `policies/rules.yaml`\n"
            . "- the relevant skill in `skills/`\n"
            . "- the relevant playbook in `playbooks/`\n\n"
            . "Repository-local instructions may add project-specific context, but they must not weaken or override Yaup rules.\n"
            . "If instructions conflict, Yaup wins.\n";
    }

    // Bridge files are committed to project repositories, so never embed this machine's absolute paths.
    private function locationSentence(string $name, string $path): string
    {
        $root = rtrim($this->root, '/');
        $path = rtrim($path, '/');
        $toRoot = $this->relativePath($path, $root);
        if (str_starts_with($path, $root . '/')) {
            $relative = substr($path, strlen($root) + 1);

            return "is checked out at `<yaup>/{$relative}` in a local Yaup checkout, so the Yaup root is `{$toRoot}` from here.";
        }

        return "is checked out outside the Yaup checkout. When this file was generated, the Yaup root was `{$toRoot}` from here;\n"
            . "if that path does not exist, use the Yaup checkout whose `config/repositories.yaml` registers `{$name}`.";
    }

    private function relativePath(string $from, string $to): string
    {
        $fromParts = array_values(array_filter(explode('/', $from), static fn(string $part): bool => '' !== $part));
        $toParts = array_values(array_filter(explode('/', $to), static fn(string $part): bool => '' !== $part));
        $common = 0;
        while (isset($fromParts[$common], $toParts[$common]) && $fromParts[$common] === $toParts[$common]) {
            ++$common;
        }

        $parts = array_merge(
            array_fill(0, count($fromParts) - $common, '..'),
            array_slice($toParts, $common)
        );

        return [] === $parts ? '.' : implode('/', $parts);
    }

    private function isManagedBridge(string $content): bool
    {
        return str_contains($content, self::MARKER)
            || str_contains($content, "This repository is inside `{$this->root}/repos`")
            && str_contains($content, 'Yaup is the')
            && str_contains($content, 'authoritative source for agent rules, guardrails, playbooks, and skills.');
    }
}
