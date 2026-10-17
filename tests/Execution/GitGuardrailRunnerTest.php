<?php

declare(strict_types=1);

namespace Yaup\Tests\Execution;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Yaup\Config\ConfigLoader;
use Yaup\Execution\GitGuardrailRunner;
use Yaup\Tests\Support\TemporaryDirectory;

final class GitGuardrailRunnerTest extends TestCase
{
    use TemporaryDirectory;

    private string $project;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        $this->project = $this->temporaryDirectory . '/project';
        mkdir($this->project);
        mkdir($this->project . '/src');
        mkdir($this->project . '/tests');
        file_put_contents($this->project . '/AGENTS.md', '# Instructions');
        file_put_contents($this->project . '/src/Feature.php', '<?php return true;');
        file_put_contents($this->project . '/.yaup.yaml', "git_guardrails: {}\n");
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

    public function testCleanWorkingTreeAndExpectedBranchPass(): void
    {
        $this->writeConfig(<<<'YAML'
            task_start:
              planning:
                clean_working_tree: block
                expected_branch:
                  patterns: ['feature/*']
                  action: block
            YAML);

        self::assertSame([], $this->runner()->inspectTaskStart($this->project, 'planning'));
    }

    public function testDirtyWorkingTreeReportsTrackedAndUntrackedPathsWithoutChangingThem(): void
    {
        $this->writeConfig(<<<'YAML'
            task_start:
              execution:
                clean_working_tree: block
            YAML);
        file_put_contents($this->project . '/src/Feature.php', '<?php return false;');
        file_put_contents($this->project . '/scratch.txt', 'user data');

        $findings = $this->runner()->inspectTaskStart($this->project, 'execution');

        self::assertCount(1, $findings);
        self::assertSame('block', $findings[0]->action);
        self::assertSame(['src/Feature.php', 'scratch.txt'], $findings[0]->paths);
        self::assertTrue($this->runner()->blocks($findings));
        self::assertSame('<?php return false;', file_get_contents($this->project . '/src/Feature.php'));
        self::assertSame('user data', file_get_contents($this->project . '/scratch.txt'));
    }

    public function testDirtyWorkingTreeCanWarnAndContinue(): void
    {
        $this->writeConfig(<<<'YAML'
            task_start:
              planning:
                clean_working_tree: warn
            YAML);
        file_put_contents($this->project . '/scratch.txt', 'user data');

        $findings = $this->runner()->inspectTaskStart($this->project, 'planning');

        self::assertSame('warn', $findings[0]->action);
        self::assertSame(['scratch.txt'], $findings[0]->paths);
        self::assertFalse($this->runner()->blocks($findings));
    }

    public function testUnexpectedBranchIsReportedAndCanWarn(): void
    {
        $this->git(['switch', '-q', 'main']);
        $this->writeConfig(<<<'YAML'
            task_start:
              planning:
                expected_branch:
                  patterns: ['feature/*']
                  action: warn
            YAML);

        $findings = $this->runner()->inspectTaskStart($this->project, 'planning');

        self::assertCount(1, $findings);
        self::assertSame('unexpected-branch', $findings[0]->id);
        self::assertSame('warn', $findings[0]->action);
        self::assertSame(['branch: main'], $findings[0]->paths);
        self::assertFalse($this->runner()->blocks($findings));
    }

    public function testBroadSourceDiffWithoutTestsReportsPathsAndRequiresResolution(): void
    {
        $this->writeConfig(<<<'YAML'
            diff_review:
              rules:
                - id: broad-source-change
                  changed_paths: ['src/**']
                  test_paths: ['tests/**']
                  minimum_files: 3
                  action: require_resolution
            YAML);
        foreach (['One', 'Two', 'Three'] as $name) {
            file_put_contents($this->project . '/src/' . $name . '.php', '<?php return true;');
        }

        $findings = $this->runner()->inspectDiff($this->project);

        self::assertCount(1, $findings);
        self::assertSame('broad-source-change', $findings[0]->id);
        self::assertSame('require_resolution', $findings[0]->action);
        self::assertSame(['src/One.php', 'src/Three.php', 'src/Two.php'], $findings[0]->paths);
        self::assertStringContainsString('no changed path matches test_paths', $findings[0]->message);
        self::assertTrue($this->runner()->blocks($findings));
    }

    public function testTestUpdatePreventsDiffReviewFalsePositive(): void
    {
        $this->writeConfig(<<<'YAML'
            diff_review:
              rules:
                - id: broad-source-change
                  changed_paths: ['src/**']
                  test_paths: ['tests/**']
                  minimum_files: 3
                  action: block
            YAML);
        foreach (['One', 'Two', 'Three'] as $name) {
            file_put_contents($this->project . '/src/' . $name . '.php', '<?php return true;');
        }
        file_put_contents($this->project . '/tests/FeatureTest.php', '<?php exit(0);');

        self::assertSame([], $this->runner()->inspectDiff($this->project));
    }

    public function testCommittedBranchChangesAreIncludedInDiffReview(): void
    {
        $this->writeConfig(<<<'YAML'
            diff_review:
              rules:
                - id: broad-source-change
                  changed_paths: ['src/**']
                  test_paths: ['tests/**']
                  minimum_files: 3
                  action: block
            YAML);
        foreach (['One', 'Two', 'Three'] as $name) {
            file_put_contents($this->project . '/src/' . $name . '.php', '<?php return true;');
        }
        $this->commitAll('add broad source changes');

        self::assertCount(1, $this->runner()->inspectDiff($this->project));
    }

    public function testDiffBelowConfiguredThresholdDoesNotTrigger(): void
    {
        $this->writeConfig(<<<'YAML'
            diff_review:
              rules:
                - id: broad-source-change
                  changed_paths: ['src/**']
                  test_paths: ['tests/**']
                  minimum_files: 3
                  action: block
            YAML);
        file_put_contents($this->project . '/src/One.php', '<?php return true;');
        file_put_contents($this->project . '/src/Two.php', '<?php return true;');

        self::assertSame([], $this->runner()->inspectDiff($this->project));
    }

    public function testUnknownPolicySettingFailsClosed(): void
    {
        $this->writeConfig("task_start:\n  planning:\n    clean_working_tre: block\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown settings: clean_working_tre');
        $this->runner()->inspectTaskStart($this->project, 'planning');
    }

    private function runner(): GitGuardrailRunner
    {
        return new GitGuardrailRunner(new ConfigLoader());
    }

    private function writeConfig(string $yaml): void
    {
        $body = implode("\n", array_map(
            static fn(string $line): string => '' === $line ? '' : '  ' . $line,
            explode("\n", rtrim($yaml)),
        ));
        file_put_contents($this->project . '/.yaup.yaml', "git_guardrails:\n{$body}\n");
        $this->commitAll('configure guardrails');
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
