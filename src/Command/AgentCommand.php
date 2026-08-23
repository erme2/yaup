<?php

declare(strict_types=1);

namespace Yaup\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Yaup\Agent\AdapterRegistry;
use Yaup\Config\ConfigLoader;
use Yaup\Plan\PlanVerifier;
use Yaup\Rules\RuleResolver;

#[AsCommand(name: 'agent', description: 'Launch a supported agent in mechanically constrained plan or execution mode')]
final class AgentCommand extends Command
{
    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('agent', InputArgument::REQUIRED)
            ->addArgument('project', InputArgument::REQUIRED)
            ->addArgument('prompt', InputArgument::REQUIRED)
            ->addOption('execute', null, InputOption::VALUE_NONE)
            ->addOption('plan', null, InputOption::VALUE_REQUIRED, 'Committed approved plan required for execution');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agentName = $input->getArgument('agent');
        $projectArgument = $input->getArgument('project');
        $promptArgument = $input->getArgument('prompt');
        if (!is_string($agentName) || !is_string($projectArgument) || !is_string($promptArgument)) {
            return Command::INVALID;
        }
        $loader = new ConfigLoader();
        $config = $loader->load($this->root . '/config/yaup.yaml');
        $projectsDirectory = $config['projects_directory'] ?? 'repos';
        $registryFile = $config['registry_file'] ?? 'config/repositories.yaml';
        if (!is_string($projectsDirectory) || '' === $projectsDirectory || !is_string($registryFile) || '' === $registryFile) {
            $output->writeln('<error>config/yaup.yaml projects_directory and registry_file must be non-empty strings.</error>');
            return Command::FAILURE;
        }
        $project = realpath($projectArgument) ?: $projectArgument;
        $registeredProjects = $this->registeredProjectPaths($loader, $this->root . '/' . $registryFile);
        if (!in_array($this->normalizePath($project), $registeredProjects, true)) {
            $output->writeln(sprintf(
                '<error>Project must be a registered checkout in %s so humans can inspect changes before Git operations.</error>',
                $this->root . '/' . $projectsDirectory
            ));
            return Command::FAILURE;
        }
        $agent = (new AdapterRegistry())->get($agentName);
        $prompt = $promptArgument;
        $resolved = (new RuleResolver($loader))->resolve($this->root, $project);
        $context = "\n\nEffective yaup rules:\n" . json_encode($resolved->rules, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            . "\nNative instruction files (read and obey):\n" . implode("\n", $resolved->nativeFiles);
        if ($input->getOption('execute')) {
            $plan = $input->getOption('plan');
            if (!is_string($plan) || '' === $plan) {
                $output->writeln('<error>--plan is required in execution mode.</error>');
                return Command::INVALID;
            }
            $verification = (new PlanVerifier(new ConfigLoader()))->verify($project, $plan);
            if (!$verification->valid) {
                $output->writeln('<error>' . implode("\n", $verification->errors) . '</error>');
                return Command::FAILURE;
            }
            $command = $agent->executeCommand($project, $prompt . $context);
        } else {
            $command = $agent->planCommand($project, $prompt . $context . "\nDo not modify files or external state.");
        }
        $process = new Process($command, $project, null, null, null);
        $process->setTty(Process::isTtySupported());
        return $process->run(static fn(string $type, string $data) => $output->write($data));
    }

    /** @return list<string> */
    private function registeredProjectPaths(ConfigLoader $loader, string $registryPath): array
    {
        $registry = $loader->load($registryPath);
        $repositories = $registry['repositories'] ?? [];
        if (!is_array($repositories)) {
            return [];
        }

        $paths = [];
        foreach ($repositories as $repository) {
            if (!is_array($repository) || !isset($repository['path']) || !is_string($repository['path'])) {
                continue;
            }

            $paths[] = $this->normalizePath(realpath($repository['path']) ?: $repository['path']);
        }

        return array_values(array_unique($paths));
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
