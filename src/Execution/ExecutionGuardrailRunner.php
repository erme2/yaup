<?php

declare(strict_types=1);

namespace Yaup\Execution;

use RuntimeException;
use Symfony\Component\Process\Process;
use Yaup\Config\ConfigLoader;

final class ExecutionGuardrailRunner
{
    public function __construct(private readonly ConfigLoader $loader) {}

    public function prepare(string $project, string $planPath): ExecutionGuardrailSet
    {
        $configPath = $project . '/.yaup.yaml';
        if (!is_file($configPath)) {
            return new ExecutionGuardrailSet([], []);
        }

        $config = $this->loader->load($configPath);
        $guardrails = $config['execution_guardrails'] ?? [];
        if (!is_array($guardrails)) {
            throw new RuntimeException('execution_guardrails must be a mapping.');
        }

        $testFirst = $guardrails['test_first'] ?? [];
        if (!is_array($testFirst) || !array_is_list($testFirst)) {
            throw new RuntimeException('execution_guardrails.test_first must be a list.');
        }
        $requiredValidation = $this->stringList($guardrails['required_validation'] ?? [], 'execution_guardrails.required_validation');
        if ([] === $testFirst && [] === $requiredValidation) {
            return new ExecutionGuardrailSet([], []);
        }

        $plan = $this->loader->load($planPath);
        $files = $this->planFiles($plan);
        $commands = $this->planCommands($plan);
        if ([] !== $testFirst && [] === $files) {
            throw new RuntimeException('The approved plan must list repository-relative paths under scope.files before test-first guardrails can be evaluated.');
        }
        $applicable = [];

        foreach ($testFirst as $index => $definition) {
            if (!is_array($definition)) {
                throw new RuntimeException("execution_guardrails.test_first[{$index}] must be a mapping.");
            }
            $sourcePaths = $this->stringList($definition['source_paths'] ?? [], "execution_guardrails.test_first[{$index}].source_paths");
            $testFiles = $this->stringList($definition['test_files'] ?? [], "execution_guardrails.test_first[{$index}].test_files");
            $command = $this->requiredString($definition['command'] ?? null, "execution_guardrails.test_first[{$index}].command");
            $expectedFailure = $this->requiredString($definition['expected_failure'] ?? null, "execution_guardrails.test_first[{$index}].expected_failure");
            foreach ($sourcePaths as $pattern) {
                $this->assertRelativePath($pattern, "execution_guardrails.test_first[{$index}].source_paths");
            }
            if ([] === $sourcePaths || [] === $testFiles) {
                throw new RuntimeException("execution_guardrails.test_first[{$index}] requires source_paths and test_files.");
            }

            $matchedFiles = array_values(array_filter($files, fn(string $file): bool => $this->matchesAny($file, $sourcePaths)));
            if ([] === $matchedFiles) {
                continue;
            }
            if (!in_array($command, $commands, true)) {
                throw new RuntimeException("The approved plan verification_plan must list the test-first command for {$matchedFiles[0]}: {$command}");
            }
            foreach ($testFiles as $testFile) {
                $relative = $this->assertRelativePath($testFile, "execution_guardrails.test_first[{$index}].test_files");
                $path = $project . '/' . $relative;
                if (!is_file($path) || !is_readable($path) || '' === trim((string) file_get_contents($path))) {
                    throw new RuntimeException("Required test file must exist, be readable, and contain content before execution: {$relative}");
                }
            }

            $applicable[] = ['command' => $command, 'expected_failure' => $expectedFailure];
        }

        foreach ($requiredValidation as $command) {
            if (!in_array($command, $commands, true)) {
                throw new RuntimeException("The approved plan verification_plan must list required validation command: {$command}");
            }
        }

        foreach ($applicable as $check) {
            $result = $this->runCommand($project, $check['command']);
            if ($result['success']) {
                throw new RuntimeException("Test-first command passed before execution; it must fail on the configured regression marker: {$check['command']}");
            }
            if (!str_contains($result['output'], $check['expected_failure'])) {
                throw new RuntimeException("Test-first command failed before execution but did not report the configured regression marker `{$check['expected_failure']}`: {$check['command']}");
            }
        }

        return new ExecutionGuardrailSet($applicable, $requiredValidation);
    }

    /** @return list<string> */
    public function verifyAfter(string $project, ExecutionGuardrailSet $guardrails): array
    {
        $errors = [];
        foreach ($guardrails->testFirst as $check) {
            $result = $this->runCommand($project, $check['command']);
            if (!$result['success']) {
                $errors[] = "Test-first command did not pass after execution: {$check['command']}";
            }
        }
        foreach ($guardrails->requiredValidation as $command) {
            $result = $this->runCommand($project, $command);
            if (!$result['success']) {
                $errors[] = "Required validation command failed after execution: {$command}";
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $plan
     *  @return list<string>
     */
    private function planFiles(array $plan): array
    {
        $files = [];
        $scope = $plan['scope'] ?? [];
        if (is_array($scope) && isset($scope['files'])) {
            $files = [...$files, ...$this->stringList($scope['files'], 'Plan scope.files')];
        }
        $changes = $plan['changes'] ?? [];
        if (is_array($changes)) {
            foreach ($changes as $change) {
                if (is_array($change) && isset($change['path']) && is_string($change['path'])) {
                    $files[] = $change['path'];
                }
            }
        }
        foreach ($files as $index => $file) {
            $files[$index] = $this->assertRelativePath($file, 'Plan file paths');
        }

        return array_values(array_unique($files));
    }

    /** @param array<string, mixed> $plan
     *  @return list<string>
     */
    private function planCommands(array $plan): array
    {
        $commands = [];
        foreach (['verification_plan', 'validation'] as $key) {
            $items = $plan[$key] ?? [];
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (is_string($item)) {
                    $commands[] = trim($item);
                } elseif (is_array($item) && isset($item['command']) && is_string($item['command'])) {
                    $commands[] = trim($item['command']);
                }
            }
        }

        return array_values(array_unique(array_filter($commands, static fn(string $command): bool => '' !== $command)));
    }

    /** @return array{success: bool, output: string} */
    private function runCommand(string $project, string $command): array
    {
        $process = Process::fromShellCommandline($command, $project);
        $process->setTimeout(900);
        try {
            $process->run();
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'output' => $process->getErrorOutput() . $process->getOutput() . $exception->getMessage(),
            ];
        }

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getErrorOutput() . $process->getOutput(),
        ];
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

    private function assertRelativePath(string $path, string $field): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ('' === $path || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            throw new RuntimeException("{$field} paths must be relative to the project root.");
        }
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        if ('' === $path || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            throw new RuntimeException("{$field} paths must be relative to the project root.");
        }
        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                throw new RuntimeException("{$field} paths cannot escape the project root.");
            }
        }

        return $path;
    }

    /** @param list<string> $patterns */
    private function matchesAny(string $path, array $patterns): bool
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
