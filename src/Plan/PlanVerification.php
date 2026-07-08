<?php

declare(strict_types=1);

namespace Yaup\Plan;

final readonly class PlanVerification
{
    /** @param list<string> $errors */
    public function __construct(public bool $valid, public array $errors, public ?string $approvalCommit) {}
}
