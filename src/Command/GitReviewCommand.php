<?php

declare(strict_types=1);

namespace Yaup\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yaup\Config\ConfigLoader;
use Yaup\Execution\GitGuardrailFinding;
use Yaup\Execution\GitGuardrailRunner;
use Yaup\Repository\Registry;

#[AsCommand(name: 'git:review', description: 'Review configured Git diff risks before approving changes')]
final class GitReviewCommand extends Command
{
    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('project', InputArgument::REQUIRED)
            ->addOption('acknowledge', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Explicitly resolve a finding that uses require_resolution');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectArgument = $input->getArgument('project');
        $acknowledgements = $input->getOption('acknowledge');
        if (!is_string($projectArgument) || !is_array($acknowledgements)) {
            return Command::INVALID;
        }
        foreach ($acknowledgements as $acknowledgement) {
            if (!is_string($acknowledgement)) {
                return Command::INVALID;
            }
        }

        $loader = new ConfigLoader();
        $project = realpath($projectArgument) ?: $projectArgument;
        try {
            $registeredPaths = array_map(
                fn(string $path): string => $this->normalizePath(realpath($path) ?: $path),
                array_column((new Registry($loader))->registeredPaths($this->root), 'path'),
            );
            if (!in_array($this->normalizePath($project), $registeredPaths, true)) {
                $io->error('Project must be a registered checkout so humans can inspect changes before Git operations.');

                return Command::FAILURE;
            }
            $findings = (new GitGuardrailRunner($loader))->inspectDiff($project);
        } catch (RuntimeException $exception) {
            $io->error('Git diff review failed: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        if ([] === $findings) {
            if ([] !== $acknowledgements) {
                $io->error('No current review finding can be acknowledged.');

                return Command::FAILURE;
            }
            $io->success('No configured Git diff risks were found.');

            return Command::SUCCESS;
        }

        $findingsById = [];
        foreach ($findings as $finding) {
            $findingsById[$finding->id] = $finding;
            $io->text(sprintf('[%s] %s: %s', $finding->action, $finding->id, $finding->message));
            foreach ($finding->paths as $path) {
                $io->text('  - ' . $path);
            }
        }

        $acknowledged = array_values(array_unique($acknowledgements));
        $unknownIds = array_diff($acknowledged, array_keys($findingsById));
        if ([] !== $unknownIds) {
            $io->error('Cannot acknowledge findings that are not present in the current diff: ' . implode(', ', $unknownIds) . '.');

            return Command::FAILURE;
        }

        foreach ($acknowledged as $id) {
            if ('require_resolution' !== $findingsById[$id]->action) {
                $io->error("Finding {$id} does not allow acknowledgement; resolve the blocking condition in the diff.");

                return Command::FAILURE;
            }
            $io->text("Explicitly acknowledged {$id}.");
        }

        $unresolvedFindings = array_filter(
            $findings,
            static fn(GitGuardrailFinding $finding): bool => 'block' === $finding->action
                || ('require_resolution' === $finding->action && !in_array($finding->id, $acknowledged, true)),
        );
        if ([] !== $unresolvedFindings) {
            $io->error('Resolve blocking findings or explicitly acknowledge each require_resolution finding before approval.');

            return Command::FAILURE;
        }

        $resolutionFindings = array_filter($findings, static fn(GitGuardrailFinding $finding): bool => 'require_resolution' === $finding->action);
        if ([] === $resolutionFindings) {
            $io->success('No blocking diff findings remain. Review the listed paths before approving.');

            return Command::SUCCESS;
        }
        $io->success('All require_resolution findings were explicitly acknowledged. Review the listed paths before approving.');

        return Command::SUCCESS;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
