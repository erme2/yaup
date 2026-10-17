<?php

declare(strict_types=1);

namespace Yaup\Execution;

final readonly class GitGuardrailFinding
{
    /** @param list<string> $paths */
    public function __construct(
        public string $id,
        public string $action,
        public string $message,
        public array $paths = [],
    ) {}
}
