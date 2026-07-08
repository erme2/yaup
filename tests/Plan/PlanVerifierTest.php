<?php

declare(strict_types=1);

namespace Yaup\Tests\Plan;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Yaup\Config\ConfigLoader;
use Yaup\Plan\PlanVerifier;
use Yaup\Tests\Support\TemporaryDirectory;

final class PlanVerifierTest extends TestCase
{
    use TemporaryDirectory;
    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        $this->git(['init']);
        $this->git(['config', 'user.email', 'test@example.com']);
        $this->git(['config', 'user.name', 'Human']);
    }
    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    public function testCommittedApprovalIsValidAndLaterModificationIsRejected(): void
    {
        $plan = $this->temporaryDirectory . '/plan.yaml';
        $content = "status: approved\napproval:\n  approved: true\n  approver: human\n  approved_at: '2026-07-08T10:14:00+01:00'\n";
        file_put_contents($plan, $content);
        $this->git(['add', 'plan.yaml']);
        $this->git(['commit', '-m', 'approve']);

        $verifier = new PlanVerifier(new ConfigLoader());
        self::assertTrue($verifier->verify($this->temporaryDirectory, $plan)->valid);
        file_put_contents($plan, $content . "changed: true\n");
        self::assertFalse($verifier->verify($this->temporaryDirectory, $plan)->valid);
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): void
    {
        $process = new Process(['git', '-C', $this->temporaryDirectory, ...$arguments]);
        $process->mustRun();
    }
}
