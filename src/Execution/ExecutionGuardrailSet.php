<?php

declare(strict_types=1);

namespace Yaup\Execution;

final readonly class ExecutionGuardrailSet
{
    /**
     * @param list<array{command: string, expected_failure: string}> $testFirst
     * @param list<string> $requiredValidation
     */
    public function __construct(public array $testFirst, public array $requiredValidation) {}
}
