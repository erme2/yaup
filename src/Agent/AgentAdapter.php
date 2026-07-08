<?php

declare(strict_types=1);

namespace Yaup\Agent;

interface AgentAdapter
{
    public function name(): string;

    /** @return list<string> */
    public function planCommand(string $project, string $prompt): array;

    /** @return list<string> */
    public function executeCommand(string $project, string $prompt): array;
}
