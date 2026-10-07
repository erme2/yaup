<?php

declare(strict_types=1);

namespace Yaup\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Yaup\Config\ConfigLoader;
use Yaup\Repository\Registry;
use Yaup\Repository\Repository;
use Yaup\Tests\Support\TemporaryDirectory;

final class RegistryTest extends TestCase
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

    public function testAddsOnlyRemoteBackedRepositories(): void
    {
        $path = $this->temporaryDirectory . '/repositories.yaml';
        $added = (new Registry(new ConfigLoader()))->synchronize($path, [
            new Repository('local', '/tmp/local', null),
            new Repository('remote', '/tmp/remote', 'git@github.com:owner/remote.git'),
        ]);

        self::assertSame(1, $added);
        $data = (new ConfigLoader())->load($path);
        self::assertIsArray($data['repositories']);
        self::assertIsArray($data['repositories'][0]);
        self::assertCount(1, $data['repositories']);
        self::assertSame('remote', $data['repositories'][0]['name']);
    }

    public function testReturnsRegisteredPathsByName(): void
    {
        mkdir($this->temporaryDirectory . '/config');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: config/repositories.yaml\n");
        file_put_contents(
            $this->temporaryDirectory . '/config/repositories.yaml',
            "repositories:\n  - name: example\n    path: /repos/example\n    remote: git@example.com:example/repo.git\n"
        );

        self::assertSame(
            ['example' => '/repos/example'],
            (new Registry(new ConfigLoader()))->registeredPaths($this->temporaryDirectory),
        );
    }

    public function testResolvesRegistryPathFromRootConfiguration(): void
    {
        mkdir($this->temporaryDirectory . '/config');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: data/registered.yaml\n");

        self::assertSame(
            $this->temporaryDirectory . '/data/registered.yaml',
            (new Registry(new ConfigLoader()))->path($this->temporaryDirectory),
        );
    }

    public function testRejectsEmptyRegistryFile(): void
    {
        mkdir($this->temporaryDirectory . '/config');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: ''\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('registry_file must be a non-empty string.');
        (new Registry(new ConfigLoader()))->path($this->temporaryDirectory);
    }

    public function testRejectsMalformedRegisteredRepository(): void
    {
        mkdir($this->temporaryDirectory . '/config');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: config/repositories.yaml\n");
        file_put_contents($this->temporaryDirectory . '/config/repositories.yaml', "repositories:\n  - name: example\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('repositories[0] name and path must be non-empty strings.');
        (new Registry(new ConfigLoader()))->registeredPaths($this->temporaryDirectory);
    }

    public function testSynchronizeRejectsMalformedExistingRepository(): void
    {
        $path = $this->temporaryDirectory . '/repositories.yaml';
        file_put_contents($path, "repositories:\n  - name: example\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('repositories[0] name and path must be non-empty strings.');
        (new Registry(new ConfigLoader()))->synchronize($path, []);
    }

    public function testRejectsAssociativeRegisteredRepositories(): void
    {
        mkdir($this->temporaryDirectory . '/config');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: config/repositories.yaml\n");
        file_put_contents(
            $this->temporaryDirectory . '/config/repositories.yaml',
            "repositories:\n  example:\n    name: example\n    path: /repos/example\n"
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('repositories must be a list.');
        (new Registry(new ConfigLoader()))->registeredPaths($this->temporaryDirectory);
    }

    public function testSynchronizeDoesNotCreateRegistryForInvalidDiscoveredRepository(): void
    {
        $path = $this->temporaryDirectory . '/repositories.yaml';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('repositories[0] name must remain a string array key.');
        try {
            (new Registry(new ConfigLoader()))->synchronize($path, [
                new Repository('123', '/repos/123', 'git@example.com:example/123.git'),
            ]);
        } finally {
            self::assertFileDoesNotExist($path);
        }
    }

    public function testSynchronizePreservesRegistryWhenDiscoveredRepositoryIsInvalid(): void
    {
        $path = $this->temporaryDirectory . '/repositories.yaml';
        $original = "# Keep this registry unchanged on failure.\nrepositories:\n  - name: example\n    path: /repos/example\n    remote: git@example.com:example/repo.git\n";
        file_put_contents($path, $original);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('repositories[0] name must remain a string array key.');
        try {
            (new Registry(new ConfigLoader()))->synchronize($path, [
                new Repository('valid', '/repos/valid', 'git@example.com:example/valid.git'),
                new Repository('123', '/repos/123', 'git@example.com:example/123.git'),
            ]);
        } finally {
            self::assertSame($original, file_get_contents($path));
        }
    }

    public function testRejectsNumericRegisteredRepositoryName(): void
    {
        mkdir($this->temporaryDirectory . '/config');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: config/repositories.yaml\n");
        file_put_contents(
            $this->temporaryDirectory . '/config/repositories.yaml',
            "repositories:\n  - name: '123'\n    path: /repos/example\n"
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('repositories[0] name must remain a string array key.');
        (new Registry(new ConfigLoader()))->registeredPaths($this->temporaryDirectory);
    }
}
