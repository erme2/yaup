<?php

declare(strict_types=1);

namespace Yaup\Repository;

use Symfony\Component\Process\Process;

final class RepositoryDiscoverer
{
    /** @return list<Repository> */
    public function discover(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $entries = scandir($directory);
        if (false === $entries) {
            return [];
        }

        $repositories = [];
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry || !is_dir($directory . '/' . $entry)) {
                continue;
            }

            $path = realpath($directory . '/' . $entry) ?: $directory . '/' . $entry;
            $repositories[] = new Repository($entry, $path, $this->remote($path));
        }

        usort($repositories, static fn(Repository $a, Repository $b): int => $a->name <=> $b->name);

        return $repositories;
    }

    private function remote(string $path): ?string
    {
        $process = new Process(['git', '-C', $path, 'remote', 'get-url', 'origin']);
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }

        $remote = trim($process->getOutput());

        return '' === $remote ? null : $remote;
    }
}
