<?php

declare(strict_types=1);

namespace Yaup\Repository;

use Yaup\Config\ConfigLoader;

final class Registry
{
    public function __construct(private readonly ConfigLoader $loader) {}

    public function path(string $root): string
    {
        $config = $this->loader->load($root . '/config/yaup.yaml');
        $registryFile = $config['registry_file'] ?? 'config/repositories.yaml';
        if (!is_string($registryFile) || '' === $registryFile) {
            throw new \RuntimeException('registry_file must be a non-empty string.');
        }

        return $root . '/' . $registryFile;
    }

    /** @return array<string, string> */
    public function registeredPaths(string $root): array
    {
        $registry = $this->loader->load($this->path($root));
        return $this->registeredPathsFromRows($registry['repositories'] ?? []);
    }

    /** @return array<string, string> */
    private function registeredPathsFromRows(mixed $rows): array
    {
        $validatedRows = $this->validatedRows($rows);
        $repositories = [];
        foreach ($validatedRows as $row) {
            $repositories[$row['name']] = $row['path'];
        }

        return $repositories;
    }

    /** @return list<array{raw: array<int|string, mixed>, name: string, path: string, remote: string}> */
    private function validatedRows(mixed $rows): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \RuntimeException('repositories must be a list.');
        }

        $validatedRows = [];
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

            if (!isset($row['remote']) || !is_string($row['remote']) || '' === $row['remote']) {
                throw new \RuntimeException("repositories[{$index}] remote must be a non-empty string.");
            }

            $validatedRows[] = [
                'raw' => $row,
                'name' => $row['name'],
                'path' => $row['path'],
                'remote' => $row['remote'],
            ];
        }

        return $validatedRows;
    }

    /** @param list<Repository> $discovered */
    public function synchronize(string $path, array $discovered): int
    {
        $current = is_file($path) ? $this->loader->load($path) : ['schema_version' => 1, 'repositories' => []];
        $rows = $current['repositories'] ?? [];
        $byRemote = [];
        foreach ($this->validatedRows($rows) as $row) {
            $byRemote[$row['remote']] = $row['raw'];
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
