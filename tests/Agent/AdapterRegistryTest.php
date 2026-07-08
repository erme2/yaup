<?php

declare(strict_types=1);

namespace Yaup\Tests\Agent;

use PHPUnit\Framework\TestCase;
use Yaup\Agent\AdapterRegistry;

final class AdapterRegistryTest extends TestCase
{
    public function testAllRequiredAgentsAreRegistered(): void
    {
        self::assertSame(['codex', 'claude', 'cursor', 'copilot', 'gemini'], (new AdapterRegistry())->names());
    }
}
