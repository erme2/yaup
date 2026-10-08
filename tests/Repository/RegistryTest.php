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
            [['name' => 'example', 'path' => '/repos/example']],
            (new Registry(new ConfigLoader()))->registeredPaths($this->temporaryDirectory),
        );
    }

    public function testPreservesEveryPathWhenNamesRepeat(): void
    {
        mkdir($this->temporaryDirectory . '/config');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: config/repositories.yaml\n");
        $registry = new Registry(new ConfigLoader());
        $registry->synchronize($this->temporaryDirectory . '/config/repositories.yaml', [
            new Repository('example', '/repos/first', 'git@example.com:first.git'),
            new Repository('example', '/repos/second', 'git@example.com:second.git'),
        ]);

        self::assertSame([
            ['name' => 'example', 'path' => '/repos/first'],
            ['name' => 'example', 'path' => '/repos/second'],
        ], $registry->registeredPaths($this->temporaryDirectory));
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
        $this->expectExceptionMessage('repositories[0] name and path must be non-empty strings.');
        try {
            (new Registry(new ConfigLoader()))->synchronize($path, [
                new Repository('', '/repos/invalid', 'git@example.com:example/123.git'),
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
        $this->expectExceptionMessage('repositories[0] name and path must be non-empty strings.');
        try {
            (new Registry(new ConfigLoader()))->synchronize($path, [
                new Repository('valid', '/repos/valid', 'git@example.com:example/valid.git'),
                new Repository('', '/repos/invalid', 'git@example.com:example/123.git'),
            ]);
        } finally {
            self::assertSame($original, file_get_contents($path));
        }
    }

    public function testSynchronizePreservesNumericRepositoryNames(): void
    {
        mkdir($this->temporaryDirectory . '/config');
        file_put_contents($this->temporaryDirectory . '/config/yaup.yaml', "registry_file: config/repositories.yaml\n");
        $path = $this->temporaryDirectory . '/config/repositories.yaml';
        $registry = new Registry(new ConfigLoader());
        $repositories = [
            new Repository('123', '/repos/123', 'git@example.com:example/123.git'),
            new Repository('0123', '/repos/0123', 'git@example.com:example/0123.git'),
        ];

        self::assertSame(2, $registry->synchronize($path, $repositories));
        self::assertSame(0, $registry->synchronize($path, $repositories));
        self::assertSame([
            ['name' => '0123', 'path' => '/repos/0123'],
            ['name' => '123', 'path' => '/repos/123'],
        ], $registry->registeredPaths($this->temporaryDirectory));
    }
}
