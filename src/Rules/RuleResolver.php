<?php

declare(strict_types=1);

namespace Yaup\Rules;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Yaup\Config\ConfigLoader;

final class RuleResolver
{
    public function __construct(private readonly ConfigLoader $loader) {}

    public function resolve(string $root, string $project, ?string $target = null): ResolvedRules
    {
        $global = $this->loader->load($root . '/policies/rules.yaml');
        $rows = $global['rules'] ?? [];
        if (!is_array($rows)) {
            throw new RuntimeException('policies/rules.yaml must contain a rules list.');
        }

        $rules = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['id']) && is_string($row['id'])) {
                $rules[$row['id']] = $row;
            }
        }

        $projectConfig = $project . '/.yaup.yaml';
        if (is_file($projectConfig)) {
            $config = $this->loader->load($projectConfig);
            $overrides = $config['rule_overrides'] ?? [];
            if (is_array($overrides)) {
                foreach ($overrides as $id => $override) {
                    if (!is_string($id) || !is_array($override)) {
                        continue;
                    }
                    if (array_key_exists('id', $override) && $override['id'] !== $id) {
                        throw new RuntimeException("Rule override id must match its key: {$id}");
                    }
                    if ('mandatory' === ($rules[$id]['level'] ?? null)) {
                        $this->assertSafeMandatoryOverride($id, $rules[$id], $override);
                    }
                    $rules[$id] = array_merge($rules[$id] ?? ['id' => $id], $override);
                }
            }
        }

        $local = $root . '/.yaup.local.yaml';
        if (is_file($local)) {
            $config = $this->loader->load($local);
            $preferences = $config['preferences'] ?? [];
            if (is_array($preferences)) {
                $rules['local.preferences'] = ['id' => 'local.preferences', 'level' => 'preference', 'values' => $preferences];
            }
        }

        $nativeFiles = $this->nativeFiles($project, $target ?? $project);

        return new ResolvedRules(array_values($rules), $nativeFiles, []);
    }

    /**
     * @param array<mixed> $rule
     * @param array<mixed> $override
     */
    private function assertSafeMandatoryOverride(string $id, array $rule, array $override): void
    {
        foreach ($override as $field => $value) {
            if (!is_string($field) || !in_array($field, ['enabled', 'id', 'level', 'summary'], true)) {
                throw new RuntimeException("Mandatory rule field cannot be added: {$id}.{$field}");
            }
            if ('enabled' === $field) {
                if (true !== $value) {
                    throw new RuntimeException("Mandatory rule cannot be disabled: {$id}");
                }

                continue;
            }
            if ($value !== ($rule[$field] ?? null)) {
                throw new RuntimeException("Mandatory rule field cannot be overridden: {$id}.{$field}");
            }
        }
    }

    /** @return list<string> */
    private function nativeFiles(string $project, string $target): array
    {
        $projectReal = realpath($project) ?: $project;
        $targetReal = realpath($target) ?: $target;
        if (!str_starts_with($targetReal, $projectReal)) {
            throw new RuntimeException('Target must be inside the project.');
        }

        $names = ['AGENTS.md', 'CLAUDE.md', 'GEMINI.md', '.cursorrules', '.github/copilot-instructions.md'];
        $files = [];
        $cursorRules = $project . '/.cursor/rules';
        if (is_dir($cursorRules)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cursorRules));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && 'md' === strtolower($file->getExtension())) {
                    $files[] = $file->getRealPath();
                }
            }
        }

        $directory = is_dir($targetReal) ? $targetReal : dirname($targetReal);
        while (str_starts_with($directory, $projectReal)) {
            foreach ($names as $name) {
                $candidate = $directory . '/' . $name;
                if (is_file($candidate)) {
                    $files[] = realpath($candidate) ?: $candidate;
                }
            }
            if ($directory === $projectReal) {
                break;
            }
            $directory = dirname($directory);
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }
}
