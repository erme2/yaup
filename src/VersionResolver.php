<?php

declare(strict_types=1);

namespace Yaup;

/**
 * Decides which version Yaup reports.
 *
 * Release builds embed a VERSION file in the phar root. A checkout has no such file and reports a development version.
 */
final class VersionResolver
{
    public const DEV_VERSION = '0.1.0-dev';
    public const VERSION_FILE = 'VERSION';

    private const PATTERN = '/^v?(\d+\.\d+\.\d+(?:-[0-9A-Za-z][0-9A-Za-z.-]*)?)$/';

    /** Returns the version without a leading "v", or null when the input is not a version such as v0.1.0. */
    public static function normalize(string $version): ?string
    {
        return 1 === preg_match(self::PATTERN, trim($version), $matches) ? $matches[1] : null;
    }

    public static function resolve(string $root): string
    {
        $file = $root . '/' . self::VERSION_FILE;
        if (!is_file($file)) {
            return self::DEV_VERSION;
        }

        $contents = file_get_contents($file);

        return is_string($contents) ? (self::normalize($contents) ?? self::DEV_VERSION) : self::DEV_VERSION;
    }
}
