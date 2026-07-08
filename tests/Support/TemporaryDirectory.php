<?php

declare(strict_types=1);

namespace Yaup\Tests\Support;

use Symfony\Component\Filesystem\Filesystem;

trait TemporaryDirectory
{
    private string $temporaryDirectory;

    protected function setUpTemporaryDirectory(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/yaup-test-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory, 0o777, true);
    }

    protected function tearDownTemporaryDirectory(): void
    {
        (new Filesystem())->remove($this->temporaryDirectory);
    }
}
