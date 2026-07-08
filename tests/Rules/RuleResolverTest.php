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
}
