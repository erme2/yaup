<?php

declare(strict_types=1);

namespace Yaup\Execution;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use Yaup\Config\ConfigLoader;

final class ExecutionGuardrailRunner
{
    public function __construct(private readonly ConfigLoader $loader, private readonly float $commandTimeoutSeconds = 900) {}

    public function prepare(string $project, string $planPath): ExecutionGuardrailSet
    {
        $configPath = $project . '/.yaup.yaml';
        if (!is_file($configPath)) {
            return new ExecutionGuardrailSet([], []);
        }

        $config = $this->loader->load($configPath);
        $guardrails = $config['execution_guardrails'] ?? [];
        if (!is_array($guardrails) || ([] !== $guardrails && array_is_list($guardrails))) {
            throw new RuntimeException('execution_guardrails must be a mapping.');
        }
        $unknownKeys = array_diff(array_keys($guardrails), ['test_first', 'required_validation']);
        if ([] !== $unknownKeys) {
            throw new RuntimeException('execution_guardrails contains unknown keys: ' . implode(', ', array_map('strval', $unknownKeys)) . '.');
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
        $sourcePathPatterns = [];
        $testFileHashes = [];

        foreach ($testFirst as $index => $definition) {
            if (!is_array($definition)) {
                throw new RuntimeException("execution_guardrails.test_first[{$index}] must be a mapping.");
            }
            $sourcePaths = $this->stringList($definition['source_paths'] ?? [], "execution_guardrails.test_first[{$index}].source_paths");
            $testFiles = $this->stringList($definition['test_files'] ?? [], "execution_guardrails.test_first[{$index}].test_files");
            $command = $this->requiredString($definition['command'] ?? null, "execution_guardrails.test_first[{$index}].command");
            $expectedFailure = $this->requiredString($definition['expected_failure'] ?? null, "execution_guardrails.test_first[{$index}].expected_failure");
            foreach ($sourcePaths as $patternIndex => $pattern) {
                $sourcePaths[$patternIndex] = $this->assertRelativePath($pattern, "execution_guardrails.test_first[{$index}].source_paths");
            }
            foreach ($testFiles as $testFileIndex => $testFile) {
                $testFiles[$testFileIndex] = $this->assertRelativePath($testFile, "execution_guardrails.test_first[{$index}].test_files");
            }
            if ([] === $sourcePaths || [] === $testFiles) {
                throw new RuntimeException("execution_guardrails.test_first[{$index}] requires source_paths and test_files.");
            }
            $sourcePathPatterns = [...$sourcePathPatterns, ...$sourcePaths];

            $matchedFiles = array_values(array_filter($files, fn(string $file): bool => $this->matchesAny($file, $sourcePaths)));
            if ([] === $matchedFiles) {
                continue;
            }
            if (!in_array($command, $commands, true)) {
                throw new RuntimeException("The approved plan verification_plan must list the test-first command for {$matchedFiles[0]}: {$command}");
            }
            foreach ($testFiles as $testFile) {
                $path = $project . '/' . $testFile;
                if (!is_file($path) || !is_readable($path) || '' === trim((string) file_get_contents($path))) {
                    throw new RuntimeException("Required test file must exist, be readable, and contain content before execution: {$testFile}");
                }
                $testFileHashes[$testFile] = $this->fileHash($path, "Required test file is unreadable: {$testFile}");
            }

            $applicable[] = ['command' => $command, 'expected_failure' => $expectedFailure, 'test_files' => $testFiles];
        }

        foreach ($requiredValidation as $command) {
            if (!in_array($command, $commands, true)) {
                throw new RuntimeException("The approved plan verification_plan must list required validation command: {$command}");
            }
        }

        $sourcePathPatterns = array_values(array_unique($sourcePathPatterns));
        $this->assertNoGuardedSymlinkDirectories($project, $sourcePathPatterns);
        $sourceHashes = $this->matchingFileHashes($project, $sourcePathPatterns);
        foreach ($applicable as $check) {
            $result = $this->runCommand($project, $check['command']);
            if (!$result['completed']) {
                throw new RuntimeException("Test-first command did not complete before execution: {$check['command']}");
            }
            if ($result['success']) {
                throw new RuntimeException("Test-first command passed before execution; it must fail on the configured regression marker: {$check['command']}");
            }
            if (!str_contains($result['output'], $check['expected_failure'])) {
                throw new RuntimeException("Test-first command failed before execution but did not report the configured regression marker `{$check['expected_failure']}`: {$check['command']}");
            }
        }
        $this->assertNoGuardedSymlinkDirectories($project, $sourcePathPatterns);

        return new ExecutionGuardrailSet(
            $applicable,
            $requiredValidation,
            $sourcePathPatterns,
            $files,
            $sourceHashes,
            $testFileHashes,
        );
    }

    /** @return list<string> */
    public function verifyAfter(string $project, ExecutionGuardrailSet $guardrails): array
    {
        $changedTests = $this->changedTestFiles($project, $guardrails);
        $errors = [...$this->verifyGuardedSourceScope($project, $guardrails), ...$this->testFileIntegrityErrors($changedTests)];
        foreach ($guardrails->requiredValidation as $command) {
            $result = $this->runCommand($project, $command);
            if (!$result['success']) {
                $errors[] = "Required validation command failed after execution: {$command}";
            }
        }

        $changedTests = $this->changedTestFiles($project, $guardrails);
        $errors = [...$errors, ...$this->testFileIntegrityErrors($changedTests)];
        if ([] === $changedTests) {
            $sourceHashesBeforeTests = $this->matchingFileHashes($project, $guardrails->sourcePathPatterns);
            foreach ($guardrails->testFirst as $check) {
                $result = $this->runCommand($project, $check['command']);
                if (!$result['completed'] || !$result['success']) {
                    $errors[] = "Test-first command did not pass after execution: {$check['command']}";
                }
            }
            $sourceHashesAfterTests = $this->matchingFileHashes($project, $guardrails->sourcePathPatterns);
            foreach ($this->changedPaths($sourceHashesBeforeTests, $sourceHashesAfterTests) as $path) {
                $errors[] = "Guarded source changed during focused test execution: {$path}. Rerun verification after source changes.";
            }
        }

        return array_values(array_unique([
            ...$errors,
            ...$this->verifyGuardedSourceScope($project, $guardrails),
            ...$this->testFileIntegrityErrors($changedTests),
        ]));
    }

    /** @return list<string> */
    private function verifyGuardedSourceScope(string $project, ExecutionGuardrailSet $guardrails): array
    {
        if ([] === $guardrails->sourcePathPatterns) {
            return [];
        }

        $errors = [];
        foreach ($this->guardedSymlinkDirectories($project, $guardrails->sourcePathPatterns) as $symlink) {
            $errors[] = "Guarded source patterns cannot be verified through directory symlink: {$symlink}.";
        }
        $currentHashes = $this->matchingFileHashes($project, $guardrails->sourcePathPatterns);
        $paths = array_unique([...array_keys($guardrails->sourceHashes), ...array_keys($currentHashes)]);
        foreach ($paths as $path) {
            if (($guardrails->sourceHashes[$path] ?? null) === ($currentHashes[$path] ?? null)
                || in_array($path, $guardrails->plannedFiles, true)) {
                continue;
            }
            $errors[] = "Guarded source changed outside the approved plan scope: {$path}. Update and reapprove the plan before execution.";
        }

        return $errors;
    }

    /** @param list<string> $patterns */
    private function assertNoGuardedSymlinkDirectories(string $project, array $patterns): void
    {
        $symlinks = $this->guardedSymlinkDirectories($project, $patterns);
        if ([] !== $symlinks) {
            throw new RuntimeException('Guarded source patterns cannot be evaluated through directory symlinks: ' . implode(', ', $symlinks) . '. Replace the symlink with a directory or adjust source_paths.');
        }
    }

    /** @param list<string> $patterns
     *  @return list<string>
     */
    private function guardedSymlinkDirectories(string $project, array $patterns): array
    {
        if ([] === $patterns) {
            return [];
        }

        $directory = new RecursiveDirectoryIterator($project, FilesystemIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator($directory, static function (\SplFileInfo $file): bool {
            return !($file->isDir() && '.git' === $file->getFilename());
        });
        $iterator = new RecursiveIteratorIterator($filtered);
        $symlinks = [];
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isLink() || !$file->isDir()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($project) + 1));
            if ($this->couldContainGuardedPath($relative, $patterns)) {
                $symlinks[] = $relative;
            }
        }

        return array_values(array_unique($symlinks));
    }

    /** @param list<string> $patterns */
    private function couldContainGuardedPath(string $directory, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->globMayMatchBelowDirectory(explode('/', $pattern), explode('/', $directory))) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $patternSegments
     *  @param list<string> $directorySegments
     */
    private function globMayMatchBelowDirectory(array $patternSegments, array $directorySegments, int $patternIndex = 0, int $directoryIndex = 0): bool
    {
        if ($directoryIndex >= count($directorySegments)) {
            return $patternIndex < count($patternSegments)
                || ([] !== $patternSegments && '**' === $patternSegments[array_key_last($patternSegments)]);
        }
        if ($patternIndex >= count($patternSegments)) {
            return false;
        }

        $patternSegment = $patternSegments[$patternIndex];
        if ('**' === $patternSegment) {
            return $this->globMayMatchBelowDirectory($patternSegments, $directorySegments, $patternIndex + 1, $directoryIndex)
                || $this->globMayMatchBelowDirectory($patternSegments, $directorySegments, $patternIndex, $directoryIndex + 1);
        }

        return $this->matchesAny($directorySegments[$directoryIndex], [$patternSegment])
            && $this->globMayMatchBelowDirectory($patternSegments, $directorySegments, $patternIndex + 1, $directoryIndex + 1);
    }

    /** @param array<string, string> $before
     *  @param array<string, string> $after
     *  @return list<string>
     */
    private function changedPaths(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $path) {
            if (($before[$path] ?? null) !== ($after[$path] ?? null)) {
                $changed[] = $path;
            }
        }

        return $changed;
    }

    /** @return list<string> */
    private function changedTestFiles(string $project, ExecutionGuardrailSet $guardrails): array
    {
        $changed = [];
        foreach ($guardrails->testFileHashes as $testFile => $expectedHash) {
            $path = $project . '/' . $testFile;
            if (!is_file($path) || !is_readable($path)) {
                $changed[] = $testFile;

                continue;
            }
            $actualHash = hash_file('sha256', $path);
            if (false === $actualHash || $expectedHash !== $actualHash) {
                $changed[] = $testFile;
            }
        }

        return $changed;
    }

    /** @param list<string> $changedTests
     *  @return list<string>
     */
    private function testFileIntegrityErrors(array $changedTests): array
    {
        if ([] === $changedTests) {
            return [];
        }

        return ['Configured regression test changed or was removed during execution: ' . implode(', ', $changedTests) . '. Reapprove the plan before changing configured tests.'];
    }

    /** @param list<string> $patterns
     *  @return array<string, string>
     */
    private function matchingFileHashes(string $project, array $patterns): array
    {
        if ([] === $patterns) {
            return [];
        }

        $directory = new RecursiveDirectoryIterator($project, FilesystemIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator($directory, static function (\SplFileInfo $file): bool {
            return !($file->isDir() && '.git' === $file->getFilename());
        });
        $iterator = new RecursiveIteratorIterator($filtered);
        $hashes = [];
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($project) + 1));
            if (!$this->matchesAny($relative, $patterns)) {
                continue;
            }
            $hashes[$relative] = $this->fileHash($file->getPathname(), "Guarded source file is unreadable: {$relative}");
        }

        return $hashes;
    }

    private function fileHash(string $path, string $error): string
    {
        $hash = hash_file('sha256', $path);
        if (false === $hash) {
            throw new RuntimeException($error);
        }

        return $hash;
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

    /** @return array{completed: bool, success: bool, output: string} */
    private function runCommand(string $project, string $command): array
    {
        $process = Process::fromShellCommandline($command, $project);
        $process->setTimeout($this->commandTimeoutSeconds);
        try {
            $process->run();
        } catch (RuntimeException $exception) {
            return [
                'completed' => false,
                'success' => false,
                'output' => $process->getErrorOutput() . $process->getOutput() . $exception->getMessage(),
            ];
        }

        return [
            'completed' => true,
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
            if ('' === $segment || '.' === $segment) {
                throw new RuntimeException("{$field} paths cannot contain empty or current-directory segments.");
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
