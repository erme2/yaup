<?php

declare(strict_types=1);

namespace Yaup\Tests\Execution;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yaup\Config\ConfigLoader;
use Yaup\Execution\ExecutionGuardrailRunner;
use Yaup\Tests\Support\TemporaryDirectory;

final class ExecutionGuardrailRunnerTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        mkdir($this->temporaryDirectory . '/src');
        mkdir($this->temporaryDirectory . '/tests');
        file_put_contents($this->temporaryDirectory . '/src/Feature.php', '<?php return false;');
        file_put_contents($this->temporaryDirectory . '/tests/FeatureTest.php', $this->redTest());
        file_put_contents($this->temporaryDirectory . '/.yaup.yaml', <<<'YAML'
            execution_guardrails:
              test_first:
                - source_paths:
                    - ./src/**/*.php
                  test_files:
                    - tests/FeatureTest.php
                  command: php tests/FeatureTest.php
                  expected_failure: KNOWN_REGRESSION
              required_validation:
                - php -r 'exit(0);'
            YAML);
        file_put_contents($this->temporaryDirectory . '/plan.yaml', <<<'YAML'
            scope:
              files:
                - src/Feature.php
            verification_plan:
              - php tests/FeatureTest.php
              - "php -r 'exit(0);'"
            YAML);
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    public function testRedRegressionCommandMustFailBeforeExecutionAndPassAfter(): void
    {
        $runner = new ExecutionGuardrailRunner(new ConfigLoader());
        $guardrails = $runner->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
        file_put_contents($this->temporaryDirectory . '/src/Feature.php', '<?php return true;');

        self::assertCount(1, $guardrails->testFirst);
        self::assertSame([], $runner->verifyAfter($this->temporaryDirectory, $guardrails));
    }

    public function testRedRegressionCommandMustPassAfterExecution(): void
    {
        $runner = new ExecutionGuardrailRunner(new ConfigLoader());
        $guardrails = $runner->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');

        self::assertSame(
            ['Test-first command did not pass after execution: php tests/FeatureTest.php'],
            $runner->verifyAfter($this->temporaryDirectory, $guardrails),
        );
    }

    public function testConfiguredRegressionTestCannotBeReplacedAfterPreparation(): void
    {
        $runner = new ExecutionGuardrailRunner(new ConfigLoader());
        $guardrails = $runner->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
        file_put_contents($this->temporaryDirectory . '/tests/FeatureTest.php', '<?php exit(0);');

        self::assertSame(
            ['Configured regression test changed or was removed during execution: tests/FeatureTest.php. Reapprove the plan before changing configured tests.'],
            $runner->verifyAfter($this->temporaryDirectory, $guardrails),
        );
    }

    public function testRequiredValidationMustPassAfterExecution(): void
    {
        file_put_contents(
            $this->temporaryDirectory . '/.yaup.yaml',
            str_replace("php -r 'exit(0);'", "php -r 'exit(1);'", (string) file_get_contents($this->temporaryDirectory . '/.yaup.yaml')),
        );
        file_put_contents(
            $this->temporaryDirectory . '/plan.yaml',
            str_replace("php -r 'exit(0);'", "php -r 'exit(1);'", (string) file_get_contents($this->temporaryDirectory . '/plan.yaml')),
        );
        $runner = new ExecutionGuardrailRunner(new ConfigLoader());
        $guardrails = $runner->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
        file_put_contents($this->temporaryDirectory . '/src/Feature.php', '<?php return true;');

        self::assertSame(
            ["Required validation command failed after execution: php -r 'exit(1);'"],
            $runner->verifyAfter($this->temporaryDirectory, $guardrails),
        );
    }

    public function testRequiredValidationCannotMutateGuardedSourceAfterScopeCheck(): void
    {
        $command = "php -r 'file_put_contents(\"src/Unplanned.php\", \"changed\");'";
        file_put_contents($this->temporaryDirectory . '/.yaup.yaml', <<<YAML
            execution_guardrails:
              test_first:
                - source_paths:
                    - src/**/*.php
                  test_files:
                    - tests/FeatureTest.php
                  command: php tests/FeatureTest.php
                  expected_failure: KNOWN_REGRESSION
              required_validation:
                - >-
                  {$command}
            YAML);
        file_put_contents($this->temporaryDirectory . '/plan.yaml', <<<YAML
            scope:
              files:
                - docs/readme.md
            verification_plan:
              - >-
                {$command}
            YAML);
        $runner = new ExecutionGuardrailRunner(new ConfigLoader());
        $guardrails = $runner->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');

        self::assertSame(
            ['Guarded source changed outside the approved plan scope: src/Unplanned.php. Update and reapprove the plan before execution.'],
            $runner->verifyAfter($this->temporaryDirectory, $guardrails),
        );
    }

    public function testRequiredValidationCannotReplaceRegressionTestAfterFingerprintCheck(): void
    {
        $command = "php -r 'file_put_contents(\"tests/FeatureTest.php\", \"replaced\");'";
        file_put_contents($this->temporaryDirectory . '/.yaup.yaml', <<<YAML
            execution_guardrails:
              test_first:
                - source_paths:
                    - src/**/*.php
                  test_files:
                    - tests/FeatureTest.php
                  command: php tests/FeatureTest.php
                  expected_failure: KNOWN_REGRESSION
              required_validation:
                - >-
                  {$command}
            YAML);
        file_put_contents($this->temporaryDirectory . '/plan.yaml', <<<YAML
            scope:
              files:
                - src/Feature.php
            verification_plan:
              - php tests/FeatureTest.php
              - >-
                {$command}
            YAML);
        $runner = new ExecutionGuardrailRunner(new ConfigLoader());
        $guardrails = $runner->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
        file_put_contents($this->temporaryDirectory . '/src/Feature.php', '<?php return true;');

        $errors = $runner->verifyAfter($this->temporaryDirectory, $guardrails);

        self::assertContains(
            'Configured regression test changed or was removed during execution: tests/FeatureTest.php. Reapprove the plan before changing configured tests.',
            $errors,
        );
    }

    public function testGuardedSourceRootSymlinkIsRejectedBeforeExecution(): void
    {
        rename($this->temporaryDirectory . '/src', $this->temporaryDirectory . '/implementation');
        symlink($this->temporaryDirectory . '/implementation', $this->temporaryDirectory . '/src');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be evaluated through directory symlinks');
        (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
    }

    public function testGuardedNestedSourceSymlinkIsRejectedBeforeExecution(): void
    {
        mkdir($this->temporaryDirectory . '/implementation');
        file_put_contents($this->temporaryDirectory . '/implementation/Feature.php', '<?php return false;');
        symlink($this->temporaryDirectory . '/implementation', $this->temporaryDirectory . '/src/Linked');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be evaluated through directory symlinks');
        (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
    }

    public function testGuardedSymlinkIntroducedDuringExecutionFailsPostCheck(): void
    {
        mkdir($this->temporaryDirectory . '/implementation');
        file_put_contents($this->temporaryDirectory . '/implementation/Linked.php', '<?php return true;');
        $runner = new ExecutionGuardrailRunner(new ConfigLoader());
        $guardrails = $runner->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
        file_put_contents($this->temporaryDirectory . '/src/Feature.php', '<?php return true;');
        symlink($this->temporaryDirectory . '/implementation', $this->temporaryDirectory . '/src/Linked');

        self::assertSame(
            ['Guarded source patterns cannot be verified through directory symlink: src/Linked.'],
            $runner->verifyAfter($this->temporaryDirectory, $guardrails),
        );
    }

    public function testMatchingSourceChangeRequiresTestFileBeforeExecution(): void
    {
        unlink($this->temporaryDirectory . '/tests/FeatureTest.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required test file must exist');
        (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
    }

    public function testGreenBaselineBlocksExecution(): void
    {
        file_put_contents($this->temporaryDirectory . '/tests/FeatureTest.php', '<?php exit(0);');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('passed before execution');
        (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
    }

    public function testFailureWithoutExpectedMarkerBlocksExecution(): void
    {
        file_put_contents($this->temporaryDirectory . '/tests/FeatureTest.php', "<?php fwrite(STDOUT, 'OTHER_FAILURE'); exit(1);");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not report the configured regression marker');
        (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
    }

    public function testRequiredValidationMustBeIncludedInApprovedPlan(): void
    {
        file_put_contents($this->temporaryDirectory . '/plan.yaml', "scope:\n  files:\n    - docs/readme.md\nverification_plan: []\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must list required validation command');
        (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
    }

    public function testTestFirstGuardrailRequiresPlannedPaths(): void
    {
        file_put_contents($this->temporaryDirectory . '/plan.yaml', "verification_plan:\n  - php tests/FeatureTest.php\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must list repository-relative paths under scope.files');
        (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
    }

    public function testPlanPathWithCurrentDirectorySegmentIsRejected(): void
    {
        $this->assertNonCanonicalPlanPathRejected('src/./Feature.php');
    }

    public function testPlanPathWithRepeatedSeparatorIsRejected(): void
    {
        $this->assertNonCanonicalPlanPathRejected('src//Feature.php');
    }

    public function testNonMatchingSourceChangeSkipsTestFirstCommand(): void
    {
        file_put_contents($this->temporaryDirectory . '/plan.yaml', "scope:\n  files:\n    - docs/readme.md\nverification_plan:\n  - \"php -r 'exit(0);'\"\n");

        $guardrails = (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');

        self::assertSame([], $guardrails->testFirst);
        self::assertSame(["php -r 'exit(0);'"], $guardrails->requiredValidation);
    }

    public function testGuardedSourceChangeOutsidePlanScopeFailsPostCheck(): void
    {
        file_put_contents($this->temporaryDirectory . '/plan.yaml', <<<'YAML'
            scope:
              files:
                - docs/readme.md
            verification_plan:
              - php tests/FeatureTest.php
              - "php -r 'exit(0);'"
            YAML);
        $runner = new ExecutionGuardrailRunner(new ConfigLoader());
        $guardrails = $runner->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
        file_put_contents($this->temporaryDirectory . '/src/Feature.php', '<?php return true;');

        self::assertSame(
            ['Guarded source changed outside the approved plan scope: src/Feature.php. Update and reapprove the plan before execution.'],
            $runner->verifyAfter($this->temporaryDirectory, $guardrails),
        );
    }

    public function testNewAndRenamedGuardedFilesOutsidePlanScopeFailPostCheck(): void
    {
        file_put_contents($this->temporaryDirectory . '/plan.yaml', <<<'YAML'
            scope:
              files:
                - docs/readme.md
            verification_plan:
              - php tests/FeatureTest.php
              - "php -r 'exit(0);'"
            YAML);
        $runner = new ExecutionGuardrailRunner(new ConfigLoader());
        $guardrails = $runner->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
        unlink($this->temporaryDirectory . '/src/Feature.php');
        file_put_contents($this->temporaryDirectory . '/src/NewFeature.php', '<?php return false;');

        $errors = $runner->verifyAfter($this->temporaryDirectory, $guardrails);

        self::assertCount(2, $errors);
        self::assertContains('Guarded source changed outside the approved plan scope: src/Feature.php. Update and reapprove the plan before execution.', $errors);
        self::assertContains('Guarded source changed outside the approved plan scope: src/NewFeature.php. Update and reapprove the plan before execution.', $errors);
    }

    public function testNonEmptyListIsRejectedAsGuardrailsMapping(): void
    {
        file_put_contents($this->temporaryDirectory . '/.yaup.yaml', <<<'YAML'
            execution_guardrails:
              - required_validation:
                  - composer test
            YAML);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('execution_guardrails must be a mapping');
        (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
    }

    public function testUnknownGuardrailKeyIsRejectedInsteadOfDisablingGuardrails(): void
    {
        file_put_contents($this->temporaryDirectory . '/.yaup.yaml', "execution_guardrails:\n  test_fist: []\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('execution_guardrails contains unknown keys: test_fist');
        (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
    }

    public function testTimedOutTestCommandCannotBeAcceptedAsRedBaseline(): void
    {
        $command = <<<'COMMAND'
            php -r 'fwrite(STDOUT, "KNOWN_REGRESSION"); flush(); sleep(5);'
            COMMAND;
        file_put_contents($this->temporaryDirectory . '/.yaup.yaml', <<<YAML
            execution_guardrails:
              test_first:
                - source_paths:
                    - ./src/**/*.php
                  test_files:
                    - tests/FeatureTest.php
                  command: >-
                    {$command}
                  expected_failure: KNOWN_REGRESSION
            YAML);
        file_put_contents($this->temporaryDirectory . '/plan.yaml', <<<YAML
            scope:
              files:
                - src/Feature.php
            verification_plan:
              - >-
                {$command}
            YAML);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test-first command did not complete before execution');
        (new ExecutionGuardrailRunner(new ConfigLoader(), 0.5))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
    }

    public function testTimedOutRequiredValidationIsReportedAfterExecution(): void
    {
        $command = "php -r 'sleep(5);'";
        file_put_contents($this->temporaryDirectory . '/.yaup.yaml', <<<YAML
            execution_guardrails:
              required_validation:
                - "{$command}"
            YAML);
        file_put_contents($this->temporaryDirectory . '/plan.yaml', <<<YAML
            verification_plan:
              - "{$command}"
            YAML);

        $runner = new ExecutionGuardrailRunner(new ConfigLoader(), 0.5);
        $guardrails = $runner->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');

        self::assertSame(
            ["Required validation command failed after execution: {$command}"],
            $runner->verifyAfter($this->temporaryDirectory, $guardrails),
        );
    }

    private function redTest(): string
    {
        return <<<'PHP'
            <?php
            $actual = require __DIR__ . '/../src/Feature.php';
            if (true !== $actual) {
                fwrite(STDOUT, 'KNOWN_REGRESSION');
                exit(1);
            }
            PHP;
    }

    private function assertNonCanonicalPlanPathRejected(string $path): void
    {
        file_put_contents($this->temporaryDirectory . '/plan.yaml', <<<YAML
            scope:
              files:
                - {$path}
            verification_plan:
              - php tests/FeatureTest.php
            YAML);

        try {
            (new ExecutionGuardrailRunner(new ConfigLoader()))->prepare($this->temporaryDirectory, $this->temporaryDirectory . '/plan.yaml');
            self::fail('Expected a noncanonical plan path to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('cannot contain empty or current-directory segments', $exception->getMessage());
        }
    }
}
