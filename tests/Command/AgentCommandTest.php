<?php

declare(strict_types=1);

namespace Yaup\Tests\Command;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Yaup\Agent\AgentPromptBuilder;
use Yaup\Command\AgentCommand;
use Yaup\Config\ConfigLoader;
use Yaup\Rules\RuleResolver;
use Yaup\Tests\Support\TemporaryDirectory;

final class AgentCommandTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        mkdir($this->temporaryDirectory . '/config');
        mkdir($this->temporaryDirectory . '/policies');
        mkdir($this->temporaryDirectory . '/repos');
        mkdir($this->temporaryDirectory . '/repos/example');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "projects_directory: repos\nregistry_file: config/repositories.yaml\n");
        file_put_contents(
            $this->temporaryDirectory . '/config/repositories.yaml',
            "schema_version: 1\nrepositories:\n  - name: example\n    path: {$this->temporaryDirectory}/repos/example\n    remote: git@example.com:example/repo.git\n"
        );
        file_put_contents(
            $this->temporaryDirectory . '/policies/rules.yaml',
            "rules:\n  - id: quality.human-maintainable-code\n    level: mandatory\n    summary: Keep code clear.\n"
        );
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    public function testProjectOutsideConfiguredReposDirectoryIsRejected(): void
    {
        mkdir($this->temporaryDirectory . '/outside');

        $tester = new CommandTester(new AgentCommand($this->temporaryDirectory));
        $status = $tester->execute([
            'agent' => 'codex',
            'project' => $this->temporaryDirectory . '/outside',
            'prompt' => 'fix something',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Project must be a registered checkout', $tester->getDisplay());
    }

    public function testProjectsDirectoryItselfIsRejected(): void
    {
        $tester = new CommandTester(new AgentCommand($this->temporaryDirectory));
        $status = $tester->execute([
            'agent' => 'codex',
            'project' => $this->temporaryDirectory . '/repos',
            'prompt' => 'fix something',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Project must be a registered checkout', $tester->getDisplay());
    }

    public function testNestedDirectoryInsideRegisteredProjectIsRejected(): void
    {
        mkdir($this->temporaryDirectory . '/repos/example/storage');

        $tester = new CommandTester(new AgentCommand($this->temporaryDirectory));
        $status = $tester->execute([
            'agent' => 'codex',
            'project' => $this->temporaryDirectory . '/repos/example/storage',
            'prompt' => 'fix something',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Project must be a registered checkout', $tester->getDisplay());
    }

    public function testRegisteredProjectRootPassesTheGuard(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported agent: unsupported');

        $tester = new CommandTester(new AgentCommand($this->temporaryDirectory));
        $tester->execute([
            'agent' => 'unsupported',
            'project' => $this->temporaryDirectory . '/repos/example',
            'prompt' => 'fix something',
        ]);
    }

    public function testRegisteredAgentReceivesTheCanonicalPolicyPrompt(): void
    {
        [$tester, $status] = $this->executeWithFixtureCodex([
            'agent' => 'codex',
            'project' => $this->temporaryDirectory . '/repos/example',
            'prompt' => 'Plan the task.',
        ]);

        $resolved = (new RuleResolver(new ConfigLoader()))->resolve(
            $this->temporaryDirectory,
            $this->temporaryDirectory . '/repos/example',
        );
        $expected = (new AgentPromptBuilder())->build('Plan the task.', $resolved, true);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame($expected, $tester->getDisplay());
    }

    public function testExecutionModeReceivesCanonicalPolicyAndNativeInstructions(): void
    {
        $project = $this->temporaryDirectory . '/repos/example';
        mkdir($project . '/plans');
        file_put_contents($project . '/AGENTS.md', '# Project instructions');
        $plan = $project . '/plans/task.yaml';
        file_put_contents(
            $plan,
            "status: approved\napproval:\n  approved: true\n  approver: human\n  approved_at: '2026-09-19T10:00:00+01:00'\n"
        );
        $this->git($project, ['init']);
        $this->git($project, ['config', 'user.email', 'test@example.com']);
        $this->git($project, ['config', 'user.name', 'Human']);
        $this->git($project, ['add', 'plans/task.yaml']);
        $this->git($project, ['commit', '-m', 'approve plan']);

        [$tester, $status] = $this->executeWithFixtureCodex([
            'agent' => 'codex',
            'project' => $project,
            'prompt' => 'Implement the approved task.',
            '--execute' => true,
            '--plan' => $plan,
        ]);

        $resolved = (new RuleResolver(new ConfigLoader()))->resolve($this->temporaryDirectory, $project);
        $expected = (new AgentPromptBuilder())->build('Implement the approved task.', $resolved);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame($expected, $tester->getDisplay());
        self::assertStringContainsString($project . '/AGENTS.md', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{CommandTester, int}
     */
    private function executeWithFixtureCodex(array $arguments): array
    {
        $executable = $this->temporaryDirectory . '/codex';
        file_put_contents(
            $executable,
            "#!/bin/sh\nfor argument in \"\$@\"; do\n    prompt=\$argument\ndone\nprintf '%s' \"\$prompt\"\n"
        );
        chmod($executable, 0o755);
        $originalPath = getenv('PATH');
        $fixturePath = false === $originalPath
            ? $this->temporaryDirectory
            : $this->temporaryDirectory . PATH_SEPARATOR . $originalPath;
        putenv('PATH=' . $fixturePath);

        try {
            $tester = new CommandTester(new AgentCommand($this->temporaryDirectory));
            $status = $tester->execute($arguments);
        } finally {
            putenv(false === $originalPath ? 'PATH' : 'PATH=' . $originalPath);
        }

        return [$tester, $status];
    }

    /** @param list<string> $arguments */
    private function git(string $repository, array $arguments): void
    {
        (new Process(['git', '-C', $repository, ...$arguments]))->mustRun();
    }
}
