<?php

declare(strict_types=1);

namespace Yaup\Validation;

use Symfony\Component\Process\Process;
use Yaup\Config\ConfigLoader;

final class ValidationRunner
{
    private const REQUIRED_CATEGORIES = [
        'focused-tests', 'full-tests', 'lint', 'format', 'static-analysis',
        'production-build', 'browser-ui-verification', 'bug-regression-test',
        'feature-tests', 'security-audit',
    ];

    public function __construct(private readonly ConfigLoader $loader) {}

    /** @return array<string, array{status: string, detail: string}> */
    public function run(string $project): array
    {
        $config = $this->loader->load($project . '/.yaup.yaml');
        $commands = $config['validation'] ?? [];
        $results = [];
        if (!is_array($commands)) {
            return ['configuration' => ['status' => 'failed', 'detail' => 'validation must be a mapping']];
        }
        foreach (self::REQUIRED_CATEGORIES as $category) {
            if (!array_key_exists($category, $commands)) {
                $results[$category] = ['status' => 'failed', 'detail' => 'Missing mandatory validation category'];
            }
        }
        foreach ($commands as $category => $specification) {
            if (!is_string($category) || !is_array($specification)) {
                continue;
            }
            if (isset($specification['exemption'])) {
                $exemption = $specification['exemption'];
                $valid = is_array($exemption) && isset($exemption['reason'], $exemption['expires'])
                    && is_string($exemption['reason']) && is_string($exemption['expires'])
                    && new \DateTimeImmutable($exemption['expires']) >= new \DateTimeImmutable('today');
                $results[$category] = ['status' => $valid ? 'exempt' : 'failed', 'detail' => $valid ? $exemption['reason'] : 'Invalid or expired exemption'];
                continue;
            }
            $command = $specification['command'] ?? null;
            if (!is_string($command) || '' === trim($command)) {
                $results[$category] = ['status' => 'failed', 'detail' => 'Missing command or exemption'];
                continue;
            }
            $process = Process::fromShellCommandline($command, $project);
            $process->setTimeout(900);
            $process->run();
            $results[$category] = [
                'status' => $process->isSuccessful() ? 'passed' : 'failed',
                'detail' => trim($process->isSuccessful() ? $process->getOutput() : $process->getErrorOutput() . $process->getOutput()),
            ];
        }

        return $results;
    }
}
