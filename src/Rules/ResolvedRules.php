<?php

declare(strict_types=1);

namespace Yaup\Rules;

final readonly class ResolvedRules
{
    /**
     * @param list<array<mixed>> $rules
     * @param list<string>               $nativeFiles
     * @param list<string>               $conflicts
     */
    public function __construct(public array $rules, public array $nativeFiles, public array $conflicts) {}
}
