<?php

declare(strict_types=1);

namespace Yaup\Tests\Agent;

use PHPUnit\Framework\TestCase;
use Yaup\Agent\AdapterRegistry;
use Yaup\Agent\AgentPromptBuilder;
use Yaup\Config\ConfigLoader;
use Yaup\Rules\RuleResolver;
use Yaup\Tests\Support\TemporaryDirectory;

final class AgentPolicyDistributionTest extends TestCase
{
    use TemporaryDirectory;

    private string|false $originalPath;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        $this->originalPath = getenv('PATH');

        foreach (['codex', 'claude', 'cursor-agent', 'copilot', 'gemini'] as $executable) {
            $path = $this->temporaryDirectory . '/' . $executable;
            file_put_contents($path, "#!/bin/sh\nexit 0\n");
            chmod($path, 0o755);
        }

        putenv('PATH=' . $this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        putenv(false === $this->originalPath ? 'PATH' : 'PATH=' . $this->originalPath);
        $this->tearDownTemporaryDirectory();
    }

    public function testEveryAdapterReceivesTheCanonicalAuthoringPolicyInBothModes(): void
    {
        $root = dirname(__DIR__, 2);
        $resolved = (new RuleResolver(new ConfigLoader()))->resolve($root, $root);
        $policy = array_values(array_filter(
            $resolved->rules,
            static fn(array $rule): bool => 'quality.human-maintainable-code' === ($rule['id'] ?? null),
        ));

        self::assertCount(1, $policy);
        self::assertSame('mandatory', $policy[0]['level']);

        $promptBuilder = new AgentPromptBuilder();
        $planningPrompt = $promptBuilder->build('Plan the task.', $resolved, true);
        $executionPrompt = $promptBuilder->build('Implement the approved task.', $resolved);
        $registry = new AdapterRegistry();

        foreach ($registry->names() as $name) {
            $adapter = $registry->get($name);
            self::assertTrue($this->commandContains($adapter->planCommand($root, $planningPrompt), $planningPrompt), "{$name} planning command omitted or changed shared policy.");
            self::assertTrue($this->commandContains($adapter->executeCommand($root, $executionPrompt), $executionPrompt), "{$name} execution command omitted or changed shared policy.");
        }
    }

    /** @param list<string> $command */
    private function commandContains(array $command, string $prompt): bool
    {
        foreach ($command as $argument) {
            if (str_contains($argument, $prompt)) {
                return true;
            }
        }

        return false;
    }
}
