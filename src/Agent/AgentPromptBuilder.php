<?php

declare(strict_types=1);

namespace Yaup\Agent;

use Yaup\Rules\ResolvedRules;

final class AgentPromptBuilder
{
    public function build(string $prompt, ResolvedRules $resolved, bool $readOnly = false): string
    {
        $rendered = $prompt
            . "\n\nEffective yaup rules:\n"
            . json_encode($resolved->rules, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            . "\nNative instruction files (read and obey):\n"
            . implode("\n", $resolved->nativeFiles);

        return $readOnly ? $rendered . "\nDo not modify files or external state." : $rendered;
    }
}
