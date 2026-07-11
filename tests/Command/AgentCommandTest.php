<?php

declare(strict_types=1);

namespace Yaup\Tests\Command;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yaup\Command\AgentCommand;
use Yaup\Tests\Support\TemporaryDirectory;

final class AgentCommandTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        mkdir($this->temporaryDirectory . '/config');
        mkdir($this->temporaryDirectory . '/repos');
        mkdir($this->temporaryDirectory . '/repos/example');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "projects_directory: repos\nregistry_file: config/repositories.yaml\n");
        file_put_contents(
            $this->temporaryDirectory . '/config/repositories.yaml',
            "schema_version: 1\nrepositories:\n  - name: example\n    path: {$this->temporaryDirectory}/repos/example\n    remote: git@example.com:example/repo.git\n"
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
}
