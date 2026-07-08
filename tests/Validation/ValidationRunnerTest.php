<?php

declare(strict_types=1);

namespace Yaup\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Yaup\Config\ConfigLoader;
use Yaup\Tests\Support\TemporaryDirectory;
use Yaup\Validation\ValidationRunner;

final class ValidationRunnerTest extends TestCase
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

    public function testRunsCommandsAndRejectsExpiredExemptions(): void
    {
        file_put_contents($this->temporaryDirectory . '/.yaup.yaml', "validation:\n  tests:\n    command: 'exit 0'\n  ui:\n    exemption:\n      reason: no UI\n      expires: '2020-01-01'\n");
        $results = (new ValidationRunner(new ConfigLoader()))->run($this->temporaryDirectory);
        self::assertSame('passed', $results['tests']['status']);
        self::assertSame('failed', $results['ui']['status']);
        self::assertSame('failed', $results['full-tests']['status']);
    }
}
