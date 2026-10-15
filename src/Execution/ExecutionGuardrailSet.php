<?php

declare(strict_types=1);

namespace Yaup\Execution;

final readonly class ExecutionGuardrailSet
{
    /**
     * @param list<array{command: string, expected_failure: string, test_files: list<string>}> $testFirst
     * @param list<string> $requiredValidation
     * @param list<string> $sourcePathPatterns
     * @param list<string> $plannedFiles
     * @param array<string, string> $sourceHashes
     * @param array<string, string> $testFileHashes
     */
    public function __construct(
        public array $testFirst,
        public array $requiredValidation,
        public array $sourcePathPatterns = [],
        public array $plannedFiles = [],
        public array $sourceHashes = [],
        public array $testFileHashes = [],
    ) {}
}
