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
use Yaup\Agent\AgentPromptBuilder;
use Yaup\Config\ConfigLoader;
use Yaup\Execution\ExecutionGuardrailRunner;
use Yaup\Execution\ExecutionGuardrailSet;
use Yaup\Plan\PlanVerifier;
use Yaup\Repository\Registry;
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
        if (!is_string($projectsDirectory) || '' === $projectsDirectory) {
            $output->writeln('<error>config/yaup.yaml projects_directory must be a non-empty string.</error>');
            return Command::FAILURE;
        }
        $project = realpath($projectArgument) ?: $projectArgument;
        try {
            $registeredProjects = array_map(
                fn(string $path): string => $this->normalizePath(realpath($path) ?: $path),
                array_column((new Registry($loader))->registeredPaths($this->root), 'path'),
            );
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
        if (!in_array($this->normalizePath($project), $registeredProjects, true)) {
            $output->writeln(sprintf(
                '<error>Project must be a registered checkout in %s so humans can inspect changes before Git operations.</error>',
                $this->root . '/' . $projectsDirectory
            ));
            return Command::FAILURE;
        }
        $ruleResolver = new RuleResolver($loader);
        if ([] === $ruleResolver->rootInstructionFiles($project)) {
            $output->writeln('<error>No usable recognized repository-root agent instructions were found. Add readable, non-empty instructions to any existing root instruction file. If AGENTS.md is missing, run `bin/yaup instructions:sync` to create a managed baseline; syncing preserves existing project-owned files.</error>');

            return Command::FAILURE;
        }
        $agent = (new AdapterRegistry())->get($agentName);
        $resolved = $ruleResolver->resolve($this->root, $project);
        $promptBuilder = new AgentPromptBuilder();
        $guardrailRunner = new ExecutionGuardrailRunner($loader);
        $guardrails = new ExecutionGuardrailSet([], []);
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
            try {
                $guardrails = $guardrailRunner->prepare($project, $plan);
            } catch (\RuntimeException $exception) {
                $output->writeln('<error>Execution guardrail failed: ' . $exception->getMessage() . '</error>');

                return Command::FAILURE;
            }
            $command = $agent->executeCommand($project, $promptBuilder->build($promptArgument, $resolved));
        } else {
            $command = $agent->planCommand($project, $promptBuilder->build($promptArgument, $resolved, true));
        }
        $process = new Process($command, $project, null, null, null);
        $process->setTty(Process::isTtySupported());
        $status = $process->run(static fn(string $type, string $data) => $output->write($data));
        if (!$input->getOption('execute') || Command::SUCCESS !== $status) {
            return $status;
        }

        $errors = $guardrailRunner->verifyAfter($project, $guardrails);
        if ([] !== $errors) {
            $output->writeln('<error>Execution guardrails failed after the agent finished:</error>');
            foreach ($errors as $error) {
                $output->writeln('- ' . $error);
            }

            return Command::FAILURE;
        }

        return $status;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
