<?php

declare(strict_types=1);

namespace Yaup\Plan;

use Symfony\Component\Process\Process;
use Yaup\Config\ConfigLoader;

final class PlanVerifier
{
    public function __construct(private readonly ConfigLoader $loader) {}

    public function verify(string $repository, string $planPath): PlanVerification
    {
        $errors = [];
        $plan = $this->loader->load($planPath);
        $approval = $plan['approval'] ?? [];
        if ('approved' !== ($plan['status'] ?? null) || !is_array($approval) || true !== ($approval['approved'] ?? false)) {
            $errors[] = 'Plan is not approved.';
        }
        if (!is_array($approval) || !isset($approval['approver']) || !is_string($approval['approver']) || '' === trim($approval['approver'])) {
            $errors[] = 'Approval field is missing: approver.';
        }
        $approvedAt = is_array($approval) ? ($approval['approved_at'] ?? null) : null;
        if (!$approvedAt instanceof \DateTimeInterface && !is_int($approvedAt) && (!is_string($approvedAt) || '' === trim($approvedAt))) {
            $errors[] = 'Approval field is missing: approved_at.';
        }
        if (is_string($approvedAt)) {
            try {
                new \DateTimeImmutable($approvedAt);
            } catch (\Exception) {
                $errors[] = 'approved_at is not a valid timestamp.';
            }
        }

        $relative = $this->relativePath($repository, $planPath);
        $process = new Process(['git', '-C', $repository, 'log', '-1', '--format=%H', '--', $relative]);
        $process->run();
        $commit = $process->isSuccessful() ? trim($process->getOutput()) : '';
        if ('' === $commit) {
            $errors[] = 'Plan approval is not committed.';
            $commit = null;
        } else {
            $show = new Process(['git', '-C', $repository, 'show', $commit . ':' . $relative]);
            $show->run();
            if (!$show->isSuccessful() || $show->getOutput() !== file_get_contents($planPath)) {
                $errors[] = 'Working plan differs from its latest committed approval.';
            }
        }

        return new PlanVerification([] === $errors, $errors, $commit);
    }

    private function relativePath(string $repository, string $path): string
    {
        $repository = rtrim(realpath($repository) ?: $repository, '/');
        $path = realpath($path) ?: $path;

        return ltrim(substr($path, strlen($repository)), '/');
    }
}
