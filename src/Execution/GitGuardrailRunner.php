<?php

declare(strict_types=1);

namespace Yaup\Execution;

use RuntimeException;
use Symfony\Component\Process\Process;
use Yaup\Config\ConfigLoader;

final class GitGuardrailRunner
{
    private const ACTIONS = ['block', 'warn', 'require_resolution'];

    public function __construct(private readonly ConfigLoader $loader) {}

    /** @return list<GitGuardrailFinding> */
    public function inspectTaskStart(string $project, string $stage): array
    {
        if (!in_array($stage, ['planning', 'execution'], true)) {
            throw new RuntimeException("Unsupported Git guardrail stage: {$stage}.");
        }

        $config = $this->gitGuardrails($project);
        $taskStart = array_key_exists('task_start', $config) ? $config['task_start'] : [];
        if (!is_array($taskStart) || ([] !== $taskStart && array_is_list($taskStart))) {
            throw new RuntimeException('git_guardrails.task_start must be a mapping.');
        }
        $unknownStages = array_diff(array_keys($taskStart), ['planning', 'execution']);
        if ([] !== $unknownStages) {
            throw new RuntimeException('git_guardrails.task_start contains unknown stages: ' . implode(', ', array_map('strval', $unknownStages)) . '.');
        }
        $settings = array_key_exists($stage, $taskStart) ? $taskStart[$stage] : [];
        if (!is_array($settings) || ([] !== $settings && array_is_list($settings))) {
            throw new RuntimeException("git_guardrails.task_start.{$stage} must be a mapping.");
        }
        $unknownSettings = array_diff(array_keys($settings), ['clean_working_tree', 'expected_branch']);
        if ([] !== $unknownSettings) {
            throw new RuntimeException("git_guardrails.task_start.{$stage} contains unknown settings: " . implode(', ', array_map('strval', $unknownSettings)) . '.');
        }
        if ([] === $settings) {
            return [];
        }

        $state = $this->gitState($project);
        $findings = [];
        if (array_key_exists('clean_working_tree', $settings)) {
            $action = $this->action($settings['clean_working_tree'], "git_guardrails.task_start.{$stage}.clean_working_tree", ['block', 'warn']);
            if ([] !== $state['paths']) {
                $findings[] = new GitGuardrailFinding(
                    'working-tree-dirty',
                    $action,
                    'The working tree contains existing changes. Yaup will not discard, stash, or clean them.',
                    $state['paths'],
                );
            }
        }

        if (array_key_exists('expected_branch', $settings)) {
            $branchRule = $settings['expected_branch'];
            if (!is_array($branchRule) || ([] !== $branchRule && array_is_list($branchRule))) {
                throw new RuntimeException("git_guardrails.task_start.{$stage}.expected_branch must be a mapping.");
            }
            $unknownBranchSettings = array_diff(array_keys($branchRule), ['patterns', 'action']);
            if ([] !== $unknownBranchSettings) {
                throw new RuntimeException("git_guardrails.task_start.{$stage}.expected_branch contains unknown settings: " . implode(', ', array_map('strval', $unknownBranchSettings)) . '.');
            }
            $patterns = $this->stringList($branchRule['patterns'] ?? [], "git_guardrails.task_start.{$stage}.expected_branch.patterns");
            if ([] === $patterns) {
                throw new RuntimeException("git_guardrails.task_start.{$stage}.expected_branch.patterns must not be empty.");
            }
            $action = $this->action($branchRule['action'] ?? null, "git_guardrails.task_start.{$stage}.expected_branch.action", ['block', 'warn']);
            if (!$this->matchesAnyBranch($state['branch'], $patterns)) {
                $actualBranch = '' === $state['branch'] ? '(detached HEAD)' : $state['branch'];
                $findings[] = new GitGuardrailFinding(
                    'unexpected-branch',
                    $action,
                    'Current branch ' . $actualBranch . ' does not match any expected branch pattern: ' . implode(', ', $patterns) . '.',
                    ['branch: ' . $actualBranch],
                );
            }
        }

        return $findings;
    }

    /** @return list<GitGuardrailFinding> */
    public function inspectDiff(string $project): array
    {
        $config = $this->gitGuardrails($project);
        $review = array_key_exists('diff_review', $config) ? $config['diff_review'] : [];
        if (!is_array($review) || ([] !== $review && array_is_list($review))) {
            throw new RuntimeException('git_guardrails.diff_review must be a mapping.');
        }
        $unknownReviewSettings = array_diff(array_keys($review), ['base_ref', 'rules']);
        if ([] !== $unknownReviewSettings) {
            throw new RuntimeException('git_guardrails.diff_review contains unknown settings: ' . implode(', ', array_map('strval', $unknownReviewSettings)) . '.');
        }
        $baseRef = array_key_exists('base_ref', $review) ? $this->requiredString($review['base_ref'], 'git_guardrails.diff_review.base_ref') : 'main';
        if (str_starts_with($baseRef, '-') || preg_match('/[\s\x00-\x1f]/', $baseRef)) {
            throw new RuntimeException('git_guardrails.diff_review.base_ref must be a Git ref without whitespace or control characters.');
        }
        $rules = array_key_exists('rules', $review) ? $review['rules'] : [];
        if (!is_array($rules) || !array_is_list($rules)) {
            throw new RuntimeException('git_guardrails.diff_review.rules must be a list.');
        }
        if ([] === $rules) {
            return [];
        }

        $changedPaths = array_values(array_unique([
            ...$this->gitState($project)['paths'],
            ...$this->committedDiffPaths($project, $baseRef),
        ]));
        $findings = [];
        $seenIds = [];
        foreach ($rules as $index => $rule) {
            if (!is_array($rule) || ([] !== $rule && array_is_list($rule))) {
                throw new RuntimeException("git_guardrails.diff_review.rules[{$index}] must be a mapping.");
            }
            $unknownRuleSettings = array_diff(array_keys($rule), ['id', 'changed_paths', 'test_paths', 'minimum_files', 'action']);
            if ([] !== $unknownRuleSettings) {
                throw new RuntimeException("git_guardrails.diff_review.rules[{$index}] contains unknown settings: " . implode(', ', array_map('strval', $unknownRuleSettings)) . '.');
            }
            $id = $this->requiredString($rule['id'] ?? null, "git_guardrails.diff_review.rules[{$index}].id");
            if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id) || isset($seenIds[$id])) {
                throw new RuntimeException("git_guardrails.diff_review.rules[{$index}].id must be unique and use lowercase letters, digits, dots, underscores, or hyphens.");
            }
            $seenIds[$id] = true;
            $changedPatterns = $this->stringList($rule['changed_paths'] ?? [], "git_guardrails.diff_review.rules[{$index}].changed_paths");
            $testPatterns = $this->stringList($rule['test_paths'] ?? [], "git_guardrails.diff_review.rules[{$index}].test_paths");
            if ([] === $changedPatterns || [] === $testPatterns) {
                throw new RuntimeException("git_guardrails.diff_review.rules[{$index}] requires changed_paths and test_paths.");
            }
            $minimumFiles = array_key_exists('minimum_files', $rule) ? $rule['minimum_files'] : 1;
            if (!is_int($minimumFiles) || $minimumFiles < 1) {
                throw new RuntimeException("git_guardrails.diff_review.rules[{$index}].minimum_files must be a positive integer.");
            }
            $action = $this->action($rule['action'] ?? null, "git_guardrails.diff_review.rules[{$index}].action");
            $matchingChanges = array_values(array_filter($changedPaths, fn(string $path): bool => $this->matchesAnyPath($path, $changedPatterns)));
            $hasTestChange = [] !== array_filter($changedPaths, fn(string $path): bool => $this->matchesAnyPath($path, $testPatterns));
            if (count($matchingChanges) < $minimumFiles || $hasTestChange) {
                continue;
            }
            $findings[] = new GitGuardrailFinding(
                $id,
                $action,
                count($matchingChanges) . ' changed path(s) match changed_paths and no changed path matches test_paths.',
                $matchingChanges,
            );
        }

        return $findings;
    }

    /** @param list<GitGuardrailFinding> $findings */
    public function blocks(array $findings): bool
    {
        foreach ($findings as $finding) {
            if ('warn' !== $finding->action) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $allowedActions */
    private function action(mixed $value, string $field, array $allowedActions = self::ACTIONS): string
    {
        if (!is_string($value) || !in_array($value, $allowedActions, true)) {
            throw new RuntimeException($field . ' must be one of: ' . implode(', ', $allowedActions) . '.');
        }

        return $value;
    }

    /** @return array{branch: string, paths: list<string>} */
    private function gitState(string $project): array
    {
        $branch = $this->runGit($project, ['branch', '--show-current']);
        $status = $this->runGit($project, ['status', '--porcelain=v1', '--untracked-files=all', '-z']);
        $entries = explode("\0", $status);
        $paths = [];
        for ($index = 0; $index < count($entries); ++$index) {
            $entry = $entries[$index];
            if ('' === $entry) {
                continue;
            }
            $statusCode = substr($entry, 0, 2);
            $path = substr($entry, 3);
            $paths[] = $path;
            if (str_contains($statusCode, 'R') || str_contains($statusCode, 'C')) {
                $destination = $entries[++$index] ?? '';
                if ('' !== $destination) {
                    $paths[] = $destination;
                }
            }
        }

        return ['branch' => trim($branch), 'paths' => array_values(array_unique($paths))];
    }

    /** @param list<string> $arguments */
    private function runGit(string $project, array $arguments): string
    {
        $process = new Process(['git', '-C', $project, ...$arguments]);
        $process->setTimeout(30);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('Could not inspect Git state: ' . trim($process->getErrorOutput() . $process->getOutput()));
        }

        return $process->getOutput();
    }

    /** @return list<string> */
    private function committedDiffPaths(string $project, string $baseRef): array
    {
        $output = $this->runGit($project, ['diff', '--name-only', '--no-renames', '-z', $baseRef . '...HEAD', '--']);

        return array_values(array_unique(array_filter(explode("\0", $output), static fn(string $path): bool => '' !== $path)));
    }

    /** @return array<string, mixed> */
    private function gitGuardrails(string $project): array
    {
        $configPath = $project . '/.yaup.yaml';
        if (!is_file($configPath)) {
            return [];
        }
        $config = $this->loader->load($configPath);
        $guardrails = array_key_exists('git_guardrails', $config) ? $config['git_guardrails'] : [];
        if (!is_array($guardrails) || ([] !== $guardrails && array_is_list($guardrails))) {
            throw new RuntimeException('git_guardrails must be a mapping.');
        }
        $validated = [];
        foreach ($guardrails as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('git_guardrails must be a mapping with string keys.');
            }
            $validated[$key] = $value;
        }
        $unknownSettings = array_diff(array_keys($validated), ['task_start', 'diff_review']);
        if ([] !== $unknownSettings) {
            throw new RuntimeException('git_guardrails contains unknown settings: ' . implode(', ', array_map('strval', $unknownSettings)) . '.');
        }

        return $validated;
    }

    /** @return list<string> */
    private function stringList(mixed $value, string $field): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuntimeException("{$field} must be a list of non-empty strings.");
        }
        foreach ($value as $item) {
            if (!is_string($item) || '' === trim($item)) {
                throw new RuntimeException("{$field} must be a list of non-empty strings.");
            }
        }

        return array_map('trim', $value);
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (!is_string($value) || '' === trim($value)) {
            throw new RuntimeException("{$field} must be a non-empty string.");
        }

        return trim($value);
    }

    /** @param list<string> $patterns */
    private function matchesAnyBranch(string $branch, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $branch)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $patterns */
    private function matchesAnyPath(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $quoted = preg_quote($pattern, '#');
            $regex = str_replace('\\*\\*/', '(?:.*/)?', $quoted);
            $regex = str_replace('\\*\\*', '.*', $regex);
            $regex = str_replace('\\*', '[^/]*', $regex);
            $regex = str_replace('\\?', '[^/]', $regex);
            if (1 === preg_match('#^' . $regex . '$#', $path)) {
                return true;
            }
        }

        return false;
    }
}
