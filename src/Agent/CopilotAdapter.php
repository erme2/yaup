<?php

declare(strict_types=1);

namespace Yaup\Agent;

final class CopilotAdapter extends AbstractAdapter
{
    public function __construct()
    {
        parent::__construct('copilot');
    }
    public function name(): string
    {
        return 'copilot';
    }
    public function planCommand(string $project, string $prompt): array
    {
        return [$this->executable(), '--available-tools=read,search', '--prompt', "Work in plan mode only. {$prompt}"];
    }
    public function executeCommand(string $project, string $prompt): array
    {
        return [$this->executable(), '--prompt', $prompt];
    }
}
