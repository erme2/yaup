<?php

declare(strict_types=1);

namespace Yaup\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Yaup\Command\TicketStatusCommand;
use Yaup\Tests\Support\TemporaryDirectory;

final class TicketStatusCommandTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        mkdir($this->temporaryDirectory . '/config');
        mkdir($this->temporaryDirectory . '/repos');
        mkdir($this->temporaryDirectory . '/repos/example');
        mkdir($this->temporaryDirectory . '/repos/missing-parent');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: config/repositories.yaml\n");
        file_put_contents(
            $this->temporaryDirectory . '/config/repositories.yaml',
            "schema_version: 1\nrepositories:\n  - name: example\n    path: {$this->temporaryDirectory}/repos/example\n    remote: git@example.com:example/repo.git\n  - name: missing\n    path: {$this->temporaryDirectory}/repos/missing-parent/missing\n    remote: git@example.com:example/missing.git\n"
        );

        $this->git($this->temporaryDirectory, ['init']);
        $this->git($this->temporaryDirectory, ['config', 'user.email', 'test@example.com']);
        $this->git($this->temporaryDirectory, ['config', 'user.name', 'Human']);
        file_put_contents($this->temporaryDirectory . '/README.md', "# Test\n");
        $this->git($this->temporaryDirectory, ['add', 'README.md']);
        $this->git($this->temporaryDirectory, ['commit', '-m', 'initial']);

        $this->git($this->temporaryDirectory . '/repos/example', ['init']);
        $this->git($this->temporaryDirectory . '/repos/example', ['config', 'user.email', 'test@example.com']);
        $this->git($this->temporaryDirectory . '/repos/example', ['config', 'user.name', 'Human']);
        file_put_contents($this->temporaryDirectory . '/repos/example/README.md', "# Example\n");
        $this->git($this->temporaryDirectory . '/repos/example', ['add', 'README.md']);
        $this->git($this->temporaryDirectory . '/repos/example', ['commit', '-m', 'initial']);
        $this->git($this->temporaryDirectory . '/repos/example', ['switch', '-c', 'feature/20-cross-repo-status']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    public function testReportsTicketStatusAcrossYaupAndRegisteredRepositories(): void
    {
        mkdir($this->temporaryDirectory . '/repos/example/plans');
        file_put_contents($this->temporaryDirectory . '/repos/example/plans/ticket-20.yaml', <<<'YAML'
            status: approved
            title: Work on https://github.com/erme2/yaup/issues/20
            approval:
              approved: true
              approver: erme2
              approved_at: '2026-08-31T10:30:00+01:00'
            YAML);
        file_put_contents($this->temporaryDirectory . '/repos/example/touched.txt', "dirty\n");

        $tester = new CommandTester(new TicketStatusCommand($this->temporaryDirectory));
        $status = $tester->execute(['ticket' => 'https://github.com/erme2/yaup/issues/20']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('yaup', $display);
        self::assertStringContainsString('example', $display);
        self::assertStringContainsString('missing', $display);
        self::assertStringContainsString('feature/20-cross-repo-status', $display);
        self::assertStringContainsString('ticket-20.yaml: approved, approved', $display);
        self::assertStringContainsString('dirty', $display);
        self::assertStringContainsString('missing', $display);
    }

    public function testCanFilterToSelectedProjects(): void
    {
        $tester = new CommandTester(new TicketStatusCommand($this->temporaryDirectory));
        $status = $tester->execute(['ticket' => '20', 'project' => ['example']]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('example', $display);
        self::assertStringNotContainsString('yaup', $display);
    }

    public function testUnknownProjectFailsClearly(): void
    {
        $tester = new CommandTester(new TicketStatusCommand($this->temporaryDirectory));
        $status = $tester->execute(['ticket' => '20', 'project' => ['nope']]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Unknown registered project: nope', $tester->getDisplay());
    }

    /** @param list<string> $arguments */
    private function git(string $path, array $arguments): void
    {
        $process = new Process(['git', '-C', $path, ...$arguments]);
        $process->mustRun();
    }
}
