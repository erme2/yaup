<?php

declare(strict_types=1);

namespace Yaup\Agent;

final class CursorAdapter extends AbstractAdapter
{
    public function __construct()
    {
        parent::__construct('cursor-agent');
    }
    public function name(): string
    {
        return 'cursor';
    }
    public function planCommand(string $project, string $prompt): array
    {
        return [$this->executable(), '--mode=plan', $prompt];
    }
    public function executeCommand(string $project, string $prompt): array
    {
        return [$this->executable(), '--mode=agent', $prompt];
    }
}
