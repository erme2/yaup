<?php

declare(strict_types=1);

namespace Yaup\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Yaup\Repository\RepositoryDiscoverer;
use Yaup\Tests\Support\TemporaryDirectory;

final class RepositoryDiscovererTest extends TestCase
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

    public function testDiscoversEveryDirectChildAndSortsNames(): void
    {
        mkdir($this->temporaryDirectory . '/zeta');
        mkdir($this->temporaryDirectory . '/alpha');
        file_put_contents($this->temporaryDirectory . '/not-a-project', 'x');

        $repositories = (new RepositoryDiscoverer())->discover($this->temporaryDirectory);

        self::assertSame(['alpha', 'zeta'], array_map(static fn($repository): string => $repository->name, $repositories));
        self::assertNull($repositories[0]->remote);
    }
}
