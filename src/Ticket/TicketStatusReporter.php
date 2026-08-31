<?php

declare(strict_types=1);

namespace Yaup\Ticket;

use Symfony\Component\Process\Process;
use Yaup\Config\ConfigLoader;

final class TicketStatusReporter
{
    public function __construct(
        private readonly string $root,
        private readonly ConfigLoader $loader,
    ) {}

    /**
     * @param list<string> $selectedProjects
     *
     * @return list<TicketStatus>
     */
    public function report(TicketReference $ticket, array $selectedProjects = []): array
    {
        $repositories = $this->repositories();
        $unknownProjects = array_values(array_diff($selectedProjects, array_keys($repositories)));
        if ([] !== $unknownProjects) {
            throw new \InvalidArgumentException('Unknown registered project: ' . implode(', ', $unknownProjects));
        }

        $rows = [];
        foreach ($repositories as $name => $path) {
            if ([] !== $selectedProjects && !in_array($name, $selectedProjects, true)) {
                continue;
            }

            $rows[] = $this->status($name, $path, $ticket);
        }

        return $rows;
    }

    /** @return array<string, string> */
    private function repositories(): array
    {
        $repositories = ['yaup' => $this->root];
        $config = $this->loader->load($this->root . '/config/yaup.yaml');
        $registryFile = $config['registry_file'] ?? 'config/repositories.yaml';
        if (!is_string($registryFile) || '' === $registryFile) {
            throw new \RuntimeException('registry_file must be a non-empty string.');
        }

        $registry = $this->loader->load($this->root . '/' . $registryFile);
        $registered = $registry['repositories'] ?? [];
        if (!is_array($registered)) {
            throw new \RuntimeException('repositories must be a list.');
        }

        foreach ($registered as $repository) {
            if (!is_array($repository) || !isset($repository['name'], $repository['path']) || !is_string($repository['name']) || !is_string($repository['path'])) {
                continue;
            }

            $repositories[$repository['name']] = $repository['path'];
        }

        ksort($repositories);

        return $repositories;
    }

    private function status(string $name, string $path, TicketReference $ticket): TicketStatus
    {
        if (!is_dir($path)) {
            return new TicketStatus($name, $path, 'missing', '-', [], [], '-');
        }

        if (!$this->isGitRepository($path)) {
            return new TicketStatus($name, $path, 'not git', '-', [], $this->matchingPlans($path, $ticket), '-');
        }

        return new TicketStatus(
            $name,
            $path,
            'available',
            $this->currentBranch($path),
            $this->matchingRefs($path, $ticket),
            $this->matchingPlans($path, $ticket),
            $this->worktreeStatus($path),
        );
    }

    private function isGitRepository(string $path): bool
    {
        $process = new Process(['git', '-C', $path, 'rev-parse', '--is-inside-work-tree']);
        $process->run();

        return $process->isSuccessful() && 'true' === trim($process->getOutput());
    }

    private function currentBranch(string $path): string
    {
        $branch = $this->gitOutput($path, ['branch', '--show-current']);
        if ('' !== $branch) {
            return $branch;
        }

        $commit = $this->gitOutput($path, ['rev-parse', '--short', 'HEAD']);

        return '' === $commit ? 'detached' : 'detached@' . $commit;
    }

    /** @return list<string> */
    private function matchingRefs(string $path, TicketReference $ticket): array
    {
        $output = $this->gitOutput($path, ['for-each-ref', '--format=%(refname:short)', 'refs/heads', 'refs/remotes']);
        if ('' === $output) {
            return [];
        }

        $refs = [];
        foreach (explode("\n", $output) as $ref) {
            $ref = trim($ref);
            if ('' !== $ref && $ticket->matches($ref)) {
                $refs[] = $ref;
            }
        }

        return array_values(array_unique($refs));
    }

    /** @return list<string> */
    private function matchingPlans(string $path, TicketReference $ticket): array
    {
        $plansDirectory = rtrim($path, '/') . '/plans';
        if (!is_dir($plansDirectory)) {
            return [];
        }

        $plans = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($plansDirectory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || !in_array($file->getExtension(), ['yaml', 'yml'], true)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (!is_string($contents) || !$ticket->matches($contents)) {
                continue;
            }

            $plans[] = $this->planSummary($file->getPathname());
        }

        sort($plans);

        return $plans;
    }

    private function planSummary(string $path): string
    {
        $name = basename($path);

        try {
            $plan = $this->loader->load($path);
        } catch (\RuntimeException) {
            return $name . ': unreadable';
        }

        $status = $plan['status'] ?? 'unknown';
        $approval = $plan['approval'] ?? [];
        $approved = is_array($approval) && true === ($approval['approved'] ?? false) ? 'approved' : 'unapproved';

        return sprintf('%s: %s, %s', $name, is_scalar($status) ? (string) $status : 'unknown', $approved);
    }

    /** @param list<string> $arguments */
    private function gitOutput(string $path, array $arguments): string
    {
        $process = new Process(['git', '-C', $path, ...$arguments]);
        $process->run();
        if (!$process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }

    private function worktreeStatus(string $path): string
    {
        $status = $this->gitOutput($path, ['status', '--short']);

        return '' === $status ? 'clean' : 'dirty';
    }
}
