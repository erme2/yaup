<?php

declare(strict_types=1);

namespace Yaup\Config;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    /** @return array<string, mixed> */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Configuration file not found: {$path}");
        }

        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new RuntimeException("Configuration must be a YAML mapping: {$path}");
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /** @param array<string, mixed> $data */
    public function dump(array $data): string
    {
        return Yaml::dump($data, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
