<?php

declare(strict_types=1);

namespace Yaup\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yaup\Application;
use Yaup\Tests\Support\TemporaryDirectory;
use Yaup\VersionResolver;

final class VersionResolverTest extends TestCase
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

    public function testCheckoutWithoutVersionFileReportsDevelopmentVersion(): void
    {
        self::assertSame(VersionResolver::DEV_VERSION, VersionResolver::resolve($this->temporaryDirectory));
    }

    public function testEmbeddedVersionIsReported(): void
    {
        file_put_contents($this->temporaryDirectory . '/VERSION', "0.1.0\n");

        self::assertSame('0.1.0', VersionResolver::resolve($this->temporaryDirectory));
    }

    public function testLeadingVIsRemovedFromEmbeddedVersion(): void
    {
        file_put_contents($this->temporaryDirectory . '/VERSION', 'v1.2.3');

        self::assertSame('1.2.3', VersionResolver::resolve($this->temporaryDirectory));
    }

    public function testInvalidEmbeddedVersionFallsBackToDevelopmentVersion(): void
    {
        file_put_contents($this->temporaryDirectory . '/VERSION', "not a version\n");

        self::assertSame(VersionResolver::DEV_VERSION, VersionResolver::resolve($this->temporaryDirectory));
    }

    public function testApplicationReportsEmbeddedVersion(): void
    {
        file_put_contents($this->temporaryDirectory . '/VERSION', "v0.1.0\n");

        self::assertSame('0.1.0', (new Application($this->temporaryDirectory))->getVersion());
    }

    public function testApplicationReportsDevelopmentVersionFromCheckout(): void
    {
        self::assertSame(VersionResolver::DEV_VERSION, (new Application($this->temporaryDirectory))->getVersion());
    }

    #[DataProvider('versions')]
    public function testNormalize(string $input, ?string $expected): void
    {
        self::assertSame($expected, VersionResolver::normalize($input));
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function versions(): iterable
    {
        yield 'tag' => ['v0.1.0', '0.1.0'];
        yield 'bare version' => ['10.20.30', '10.20.30'];
        yield 'trailing newline' => ["v1.2.3\n", '1.2.3'];
        yield 'pre-release' => ['v1.0.0-rc.1', '1.0.0-rc.1'];
        yield 'two parts' => ['v1.2', null];
        yield 'four parts' => ['v1.2.3.4', null];
        yield 'text' => ['latest', null];
        yield 'empty' => ['', null];
    }
}
