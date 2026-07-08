<?php

declare(strict_types=1);

namespace Yaup\Agent;

final class GeminiAdapter extends AbstractAdapter
{
    public function __construct()
    {
        parent::__construct('gemini');
    }
    public function name(): string
    {
        return 'gemini';
    }
    public function planCommand(string $project, string $prompt): array
    {
        return [$this->executable(), '--approval-mode=plan', $prompt];
    }
    public function executeCommand(string $project, string $prompt): array
    {
        return [$this->executable(), '--approval-mode=default', $prompt];
    }
}
