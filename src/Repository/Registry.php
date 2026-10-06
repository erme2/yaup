<?php

declare(strict_types=1);

namespace Yaup\Repository;

use Yaup\Config\ConfigLoader;

final class Registry
{
    public function __construct(private readonly ConfigLoader $loader) {}

    /** @return array<string, string> */
    public function registeredPaths(string $root): array
    {
        $config = $this->loader->load($root . '/config/yaup.yaml');
        $registryFile = $config['registry_file'] ?? 'config/repositories.yaml';
        if (!is_string($registryFile) || '' === $registryFile) {
            throw new \RuntimeException('registry_file must be a non-empty string.');
        }

        $registry = $this->loader->load($root . '/' . $registryFile);
        $rows = $registry['repositories'] ?? [];
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \RuntimeException('repositories must be a list.');
        }

        $repositories = [];
        foreach ($rows as $index => $row) {
            if (
                !is_array($row)
                || !isset($row['name'], $row['path'])
                || !is_string($row['name'])
                || '' === $row['name']
                || !is_string($row['path'])
                || '' === $row['path']
            ) {
                throw new \RuntimeException("repositories[{$index}] name and path must be non-empty strings.");
            }

            $keyProbe = [];
            $keyProbe[$row['name']] = true;
            if (array_key_first($keyProbe) !== $row['name']) {
                throw new \RuntimeException("repositories[{$index}] name must remain a string array key.");
            }

            $repositories[$row['name']] = $row['path'];
        }

        return $repositories;
    }

    /** @param list<Repository> $discovered */
    public function synchronize(string $path, array $discovered): int
    {
        $current = is_file($path) ? $this->loader->load($path) : ['schema_version' => 1, 'repositories' => []];
        $rows = $current['repositories'] ?? [];
        $byRemote = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['remote']) && is_string($row['remote'])) {
                    $byRemote[$row['remote']] = $row;
                }
            }
        }

        $added = 0;
        foreach ($discovered as $repository) {
            if (null === $repository->remote || isset($byRemote[$repository->remote])) {
                continue;
            }
            $byRemote[$repository->remote] = $repository->toArray();
            ++$added;
        }
        ksort($byRemote);
        $current['schema_version'] = 1;
        $current['repositories'] = array_values($byRemote);
        file_put_contents($path, $this->loader->dump($current));

        return $added;
    }
}
