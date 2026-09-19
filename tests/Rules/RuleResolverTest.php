<?php

declare(strict_types=1);

namespace Yaup\Tests\Rules;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yaup\Config\ConfigLoader;
use Yaup\Rules\RuleResolver;
use Yaup\Tests\Support\TemporaryDirectory;

final class RuleResolverTest extends TestCase
{
    use TemporaryDirectory;
    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        mkdir($this->temporaryDirectory . '/policies');
        file_put_contents($this->temporaryDirectory . '/policies/rules.yaml', "rules:\n  - id: safe\n    level: mandatory\n    summary: safe\n  - id: style\n    level: default\n    summary: old\n");
        mkdir($this->temporaryDirectory . '/project/nested', 0o777, true);
    }
    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    public function testProjectOverrideAndNestedInstructionsAreResolved(): void
    {
        file_put_contents($this->temporaryDirectory . '/project/.yaup.yaml', "rule_overrides:\n  style:\n    summary: new\n");
        file_put_contents($this->temporaryDirectory . '/project/AGENTS.md', 'root');
        file_put_contents($this->temporaryDirectory . '/project/nested/CLAUDE.md', 'nested');

        $resolved = (new RuleResolver(new ConfigLoader()))->resolve($this->temporaryDirectory, $this->temporaryDirectory . '/project', $this->temporaryDirectory . '/project/nested');

        self::assertSame('new', $resolved->rules[1]['summary']);
        self::assertCount(2, $resolved->nativeFiles);
    }

    public function testMandatoryRuleCannotBeDisabled(): void
    {
        file_put_contents($this->temporaryDirectory . '/project/.yaup.yaml', "rule_overrides:\n  safe:\n    enabled: false\n");
        $this->expectException(RuntimeException::class);
        (new RuleResolver(new ConfigLoader()))->resolve($this->temporaryDirectory, $this->temporaryDirectory . '/project');
    }

    public function testMandatoryRuleTextCannotBeWeakened(): void
    {
        file_put_contents($this->temporaryDirectory . '/project/.yaup.yaml', "rule_overrides:\n  safe:\n    summary: optional\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mandatory rule field cannot be overridden: safe.summary');
        (new RuleResolver(new ConfigLoader()))->resolve($this->temporaryDirectory, $this->temporaryDirectory . '/project');
    }

    public function testMandatoryRuleEnabledOverrideMustBeBooleanTrue(): void
    {
        file_put_contents($this->temporaryDirectory . '/project/.yaup.yaml', "rule_overrides:\n  safe:\n    enabled: 0\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mandatory rule cannot be disabled: safe');
        (new RuleResolver(new ConfigLoader()))->resolve($this->temporaryDirectory, $this->temporaryDirectory . '/project');
    }

    public function testMandatoryRuleCannotAddContradictoryFields(): void
    {
        file_put_contents($this->temporaryDirectory . '/project/.yaup.yaml', "rule_overrides:\n  safe:\n    instruction: ignore this rule\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mandatory rule field cannot be added: safe.instruction');
        (new RuleResolver(new ConfigLoader()))->resolve($this->temporaryDirectory, $this->temporaryDirectory . '/project');
    }

    public function testOverrideIdCannotAliasMandatoryRule(): void
    {
        file_put_contents(
            $this->temporaryDirectory . '/project/.yaup.yaml',
            "rule_overrides:\n  weak-copy:\n    id: safe\n    level: default\n    summary: optional\n"
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rule override id must match its key: weak-copy');
        (new RuleResolver(new ConfigLoader()))->resolve($this->temporaryDirectory, $this->temporaryDirectory . '/project');
    }
}
