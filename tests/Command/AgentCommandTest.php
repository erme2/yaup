<?php

declare(strict_types=1);

namespace Yaup\Tests\Command;

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
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "projects_directory: repos\n");
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
        self::assertStringContainsString('Project must be a checkout under', $tester->getDisplay());
    }
}
