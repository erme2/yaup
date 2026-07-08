<?php

declare(strict_types=1);

namespace Yaup\Agent;

use InvalidArgumentException;

final class AdapterRegistry
{
    /** @var array<string, AgentAdapter> */
    private array $adapters;

    public function __construct()
    {
        $this->adapters = [];
        foreach ([new CodexAdapter(), new ClaudeAdapter(), new CursorAdapter(), new CopilotAdapter(), new GeminiAdapter()] as $adapter) {
            $this->adapters[$adapter->name()] = $adapter;
        }
    }

    public function get(string $name): AgentAdapter
    {
        return $this->adapters[$name] ?? throw new InvalidArgumentException("Unsupported agent: {$name}");
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->adapters);
    }
}
