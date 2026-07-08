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
}
