<?php

declare(strict_types=1);

namespace Yaup\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yaup\Command\AgentCommand;
use Yaup\Command\DiscoverCommand;
use Yaup\Command\InstructionsSyncCommand;
use Yaup\Command\TicketStatusCommand;
use Yaup\Config\ConfigLoader;
use Yaup\Repository\Registry;
use Yaup\Tests\Support\TemporaryDirectory;

final class RegistryValidationTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        mkdir($this->temporaryDirectory . '/config');
        mkdir($this->temporaryDirectory . '/repos');
        mkdir($this->temporaryDirectory . '/repos/example');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: config/repositories.yaml\n");
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRemotes(): iterable
    {
        yield 'missing' => [''];
        yield 'empty' => ["    remote: ''\n"];
        yield 'non-string' => ["    remote: 123\n"];
    }

    #[DataProvider('invalidRemotes')]
    public function testInvalidRemoteFailsConsistentlyWithoutWriting(string $remote): void
    {
        $path = $this->temporaryDirectory . '/config/repositories.yaml';
        $original = "# Preserve this file on failure.\nrepositories:\n  - name: example\n    path: {$this->temporaryDirectory}/repos/example\n" . $remote;
        file_put_contents($path, $original);
        $message = 'repositories[0] remote must be a non-empty string.';
        $commands = [
            [new DiscoverCommand($this->temporaryDirectory), [], Command::INVALID],
            [new InstructionsSyncCommand($this->temporaryDirectory), [], Command::INVALID],
            [new TicketStatusCommand($this->temporaryDirectory), ['ticket' => '35'], Command::FAILURE],
            [new AgentCommand($this->temporaryDirectory), [
                'agent' => 'codex',
                'project' => $this->temporaryDirectory . '/repos/example',
                'prompt' => 'Plan the task.',
            ], Command::FAILURE],
        ];
        foreach ($commands as [$command, $arguments, $expectedStatus]) {
            $tester = new CommandTester($command);
            self::assertSame($expectedStatus, $tester->execute($arguments));
            self::assertStringContainsString($message, $tester->getDisplay());
            self::assertSame($original, file_get_contents($path));
            self::assertFileDoesNotExist($this->temporaryDirectory . '/repos/example/AGENTS.md');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
        (new Registry(new ConfigLoader()))->registeredPaths($this->temporaryDirectory);
    }
}
