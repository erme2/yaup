<?php

declare(strict_types=1);

namespace Yaup\Agent;

final class CodexAdapter extends AbstractAdapter
{
    public function __construct()
    {
        parent::__construct('codex');
    }

    public function name(): string
    {
        return 'codex';
    }

    public function planCommand(string $project, string $prompt): array
    {
        return [$this->executable(), '--cd', $project, '--sandbox', 'read-only', '--ask-for-approval', 'never', $prompt];
    }

    public function executeCommand(string $project, string $prompt): array
    {
        return [$this->executable(), '--cd', $project, '--sandbox', 'workspace-write', '--ask-for-approval', 'on-request', $prompt];
    }
}
