<?php

declare(strict_types=1);

namespace Yaup\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Yaup\Command\GitReviewCommand;
use Yaup\Tests\Support\TemporaryDirectory;

final class GitReviewCommandTest extends TestCase
{
    use TemporaryDirectory;

    private string $project;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        mkdir($this->temporaryDirectory . '/config');
        mkdir($this->temporaryDirectory . '/repos');
        $this->project = $this->temporaryDirectory . '/repos/example';
        mkdir($this->project);
        mkdir($this->project . '/src');
        mkdir($this->project . '/tests');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "projects_directory: repos\nregistry_file: config/repositories.yaml\n");
        file_put_contents($this->temporaryDirectory . '/config/repositories.yaml', <<<YAML
            schema_version: 1
            repositories:
              - name: example
                path: {$this->project}
                remote: git@example.com:example.git
            YAML);
        file_put_contents($this->project . '/AGENTS.md', '# Instructions');
        file_put_contents($this->project . '/.yaup.yaml', $this->reviewConfig('require_resolution'));
        file_put_contents($this->project . '/src/Feature.php', '<?php return true;');
        $this->git(['init', '-q', '-b', 'main']);
        $this->git(['config', 'user.email', 'test@example.com']);
        $this->git(['config', 'user.name', 'Yaup Test']);
        $this->commitAll('initial');
        $this->git(['switch', '-q', '-c', 'feature/39-example']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    public function testReviewRequiresAndAcceptsExplicitResolutionByFindingId(): void
    {
        $this->addBroadSourceChanges();

        [$tester, $status] = $this->runReview();
        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('[require_resolution] broad-source-change-without-tests', $tester->getDisplay());
        self::assertStringContainsString('src/Three.php', $tester->getDisplay());

        [$tester, $status] = $this->runReview(['--acknowledge' => ['broad-source-change-without-tests']]);
        self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());
        self::assertStringContainsString('Explicitly acknowledged broad-source-change-without-tests.', $tester->getDisplay());
    }

    public function testBlockingReviewFindingCannotBeAcknowledged(): void
    {
        file_put_contents($this->project . '/.yaup.yaml', $this->reviewConfig('block'));
        $this->commitAll('make review rule blocking');
        $this->addBroadSourceChanges();

        [$tester, $status] = $this->runReview(['--acknowledge' => ['broad-source-change-without-tests']]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('does not allow', $tester->getDisplay());
    }

    public function testAcknowledgingMissingFindingIdFails(): void
    {
        [$tester, $status] = $this->runReview(['--acknowledge' => ['missing-finding']]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('No current review finding can be acknowledged', $tester->getDisplay());
    }

    /** @param array<string, mixed> $options
     *  @return array{CommandTester, int}
     */
    private function runReview(array $options = []): array
    {
        $tester = new CommandTester(new GitReviewCommand($this->temporaryDirectory));
        $status = $tester->execute(['project' => $this->project, ...$options]);

        return [$tester, $status];
    }

    private function reviewConfig(string $action): string
    {
        return <<<YAML
            git_guardrails:
              diff_review:
                rules:
                  - id: broad-source-change-without-tests
                    changed_paths: ['src/**']
                    test_paths: ['tests/**']
                    minimum_files: 3
                    action: {$action}
            YAML;
    }

    private function addBroadSourceChanges(): void
    {
        foreach (['One', 'Two', 'Three'] as $name) {
            file_put_contents($this->project . '/src/' . $name . '.php', '<?php return true;');
        }
    }

    private function commitAll(string $message): void
    {
        $this->git(['add', '-A']);
        $this->git(['commit', '-q', '-m', $message]);
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): void
    {
        (new Process(['git', '-C', $this->project, ...$arguments]))->mustRun();
    }
}
