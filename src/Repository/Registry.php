<?php

declare(strict_types=1);

namespace Yaup\Repository;

use Yaup\Config\ConfigLoader;

final class Registry
{
    public function __construct(private readonly ConfigLoader $loader) {}

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
