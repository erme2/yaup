<?php

declare(strict_types=1);

namespace Yaup\Agent;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

abstract class AbstractAdapter implements AgentAdapter
{
    public function __construct(private readonly string $executable) {}

    protected function executable(): string
    {
        $path = (new ExecutableFinder())->find($this->executable);
        if (null === $path) {
            throw new RuntimeException("Agent CLI is not installed: {$this->executable}");
        }

        return $path;
    }
}
