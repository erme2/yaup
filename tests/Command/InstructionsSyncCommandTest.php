<?php

declare(strict_types=1);

namespace Yaup\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yaup\Command\InstructionsSyncCommand;
use Yaup\Tests\Support\TemporaryDirectory;

final class InstructionsSyncCommandTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        mkdir($this->temporaryDirectory . '/config');
        mkdir($this->temporaryDirectory . '/repos');
        mkdir($this->temporaryDirectory . '/repos/example');
        mkdir($this->temporaryDirectory . '/repos/other');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: config/repositories.yaml\n");
        file_put_contents(
            $this->temporaryDirectory . '/config/repositories.yaml',
            "schema_version: 1\nrepositories:\n  - name: example\n    path: {$this->temporaryDirectory}/repos/example\n    remote: git@example.com:example/repo.git\n  - name: other\n    path: {$this->temporaryDirectory}/repos/other\n    remote: git@example.com:example/other.git\n"
        );
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    public function testCreatesMissingBridgeFile(): void
    {
        $tester = new CommandTester(new InstructionsSyncCommand($this->temporaryDirectory));
        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        $content = file_get_contents($this->temporaryDirectory . '/repos/example/AGENTS.md');
        self::assertIsString($content);
        self::assertStringContainsString('<!-- yaup-managed-agent-bridge -->', $content);
        self::assertStringContainsString('registered with Yaup as `example`', $content);
        self::assertStringContainsString($this->temporaryDirectory . '/policies/core.md', $content);
        self::assertStringContainsString('created', $tester->getDisplay());
    }

    public function testCanSyncSelectedProjectsOnly(): void
    {
        $tester = new CommandTester(new InstructionsSyncCommand($this->temporaryDirectory));
        $status = $tester->execute(['project' => ['example']]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertFileExists($this->temporaryDirectory . '/repos/example/AGENTS.md');
        self::assertFileDoesNotExist($this->temporaryDirectory . '/repos/other/AGENTS.md');
        self::assertStringContainsString('example', $tester->getDisplay());
        self::assertStringNotContainsString('other', $tester->getDisplay());
    }

    public function testUnknownSelectedProjectFails(): void
    {
        $tester = new CommandTester(new InstructionsSyncCommand($this->temporaryDirectory));
        $status = $tester->execute(['project' => ['missing']]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Unknown registered project: missing', $tester->getDisplay());
    }

    public function testMixedKnownAndUnknownSelectedProjectsFailBeforeWriting(): void
    {
        $tester = new CommandTester(new InstructionsSyncCommand($this->temporaryDirectory));
        $status = $tester->execute(['project' => ['example', 'missing']]);

        self::assertSame(Command::FAILURE, $status);
        self::assertFileDoesNotExist($this->temporaryDirectory . '/repos/example/AGENTS.md');
        self::assertStringContainsString('Unknown registered project: missing', $tester->getDisplay());
    }

    public function testUpdatesManagedBridgeFile(): void
    {
        file_put_contents($this->temporaryDirectory . '/repos/example/AGENTS.md', "<!-- yaup-managed-agent-bridge -->\nold\n");

        $tester = new CommandTester(new InstructionsSyncCommand($this->temporaryDirectory));
        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        $content = file_get_contents($this->temporaryDirectory . '/repos/example/AGENTS.md');
        self::assertIsString($content);
        self::assertNotSame("<!-- yaup-managed-agent-bridge -->\nold\n", $content);
        self::assertStringContainsString('updated', $tester->getDisplay());
    }

    public function testAdoptsOldUnmarkedBridgeFile(): void
    {
        file_put_contents(
            $this->temporaryDirectory . '/repos/example/AGENTS.md',
            "# Agent Instructions\n\nThis repository is inside `{$this->temporaryDirectory}/repos`, so Yaup is the\nauthoritative source for agent rules, guardrails, playbooks, and skills.\n"
        );

        $tester = new CommandTester(new InstructionsSyncCommand($this->temporaryDirectory));
        $status = $tester->execute(['project' => ['example']]);

        self::assertSame(Command::SUCCESS, $status);
        $content = file_get_contents($this->temporaryDirectory . '/repos/example/AGENTS.md');
        self::assertIsString($content);
        self::assertStringContainsString('<!-- yaup-managed-agent-bridge -->', $content);
        self::assertStringContainsString('updated', $tester->getDisplay());
    }

    public function testPreservesProjectOwnedInstructions(): void
    {
        file_put_contents($this->temporaryDirectory . '/repos/example/AGENTS.md', "# Project Instructions\n\nKeep me.\n");

        $tester = new CommandTester(new InstructionsSyncCommand($this->temporaryDirectory));
        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame("# Project Instructions\n\nKeep me.\n", file_get_contents($this->temporaryDirectory . '/repos/example/AGENTS.md'));
        self::assertStringContainsString('preserved', $tester->getDisplay());
    }

    public function testWriteFailureFailsCommand(): void
    {
        mkdir($this->temporaryDirectory . '/repos/example/AGENTS.md');

        $tester = new CommandTester(new InstructionsSyncCommand($this->temporaryDirectory));
        $status = $tester->execute(['project' => ['example']]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('write failed', $tester->getDisplay());
        self::assertStringContainsString('Failed to write one or more Yaup agent bridge files.', $tester->getDisplay());
    }
}
