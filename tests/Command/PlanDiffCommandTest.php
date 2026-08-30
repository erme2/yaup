<?php

declare(strict_types=1);

namespace Yaup\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yaup\Command\PlanDiffCommand;
use Yaup\Tests\Support\TemporaryDirectory;

final class PlanDiffCommandTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    public function testRendersHumanReadablePlanPreview(): void
    {
        $plan = $this->temporaryDirectory . '/plan.yaml';
        file_put_contents($plan, <<<'YAML'
            status: draft
            summary: Add a health-check endpoint.
            repository: example
            target_branch: main
            changes:
              - path: routes/web.php
                action: add GET /health route
              - path: tests/Feature/HealthCheckTest.php
                action: cover JSON response
            validation:
              - php artisan test --filter=HealthCheckTest
            risks:
              - Route name collision.
            approval:
              approved: false
            YAML);

        $tester = new CommandTester(new PlanDiffCommand());
        $status = $tester->execute(['plan' => $plan]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Plan preview', $display);
        self::assertStringContainsString('Status: draft', $display);
        self::assertStringContainsString('Summary: Add a health-check endpoint.', $display);
        self::assertStringContainsString('- routes/web.php - add GET /health route', $display);
        self::assertStringContainsString('- tests/Feature/HealthCheckTest.php - cover JSON response', $display);
        self::assertStringContainsString('- php artisan test --filter=HealthCheckTest', $display);
        self::assertStringContainsString('- Route name collision.', $display);
        self::assertStringContainsString('- approved: no', $display);
    }

    public function testMissingPlanFailsClearly(): void
    {
        $tester = new CommandTester(new PlanDiffCommand());
        $status = $tester->execute(['plan' => $this->temporaryDirectory . '/missing.yaml']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Configuration file not found', $tester->getDisplay());
    }
}
