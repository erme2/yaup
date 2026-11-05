<?php

declare(strict_types=1);

if ('1' === ini_get('phar.readonly')) {
    fwrite(STDERR, "Set phar.readonly=0 to build the release artifact.\n");
    exit(1);
}

// Usage: php -d phar.readonly=0 tools/build-phar.php [version]
// The version (for example v0.1.0 or 0.1.0, also read from YAUP_VERSION) is embedded so `yaup --version`
// reports it; a leading "v" is dropped. Without a version the phar reports the development version.
$root = dirname(__DIR__);
$requested = $argv[1] ?? getenv('YAUP_VERSION');
$version = null;
if (is_string($requested) && '' !== trim($requested)) {
    require_once $root.'/vendor/autoload.php';
    $version = Yaup\VersionResolver::normalize($requested);
    if (null === $version) {
        fwrite(STDERR, sprintf("'%s' is not a version such as v0.1.0 or 0.1.0.\n", $requested));
        exit(1);
    }
}

@mkdir($root.'/dist', 0777, true);
$path = $root.'/dist/yaup.phar';
@unlink($path);
$phar = new Phar($path);
$phar->startBuffering();
$count = 0;
foreach (['src', 'vendor/composer', 'vendor/psr', 'vendor/symfony'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
    $count += count($phar->buildFromIterator($iterator, $root));
}
$phar->addFile($root.'/vendor/autoload.php', 'vendor/autoload.php');
$phar->addFile($root.'/bin/yaup', 'bin/yaup');
if (null !== $version) {
    $phar->addFromString(Yaup\VersionResolver::VERSION_FILE, $version."\n");
}
$phar->setStub("#!/usr/bin/env php\n".Phar::createDefaultStub('bin/yaup'));
$phar->stopBuffering();
if (0 === $count || !is_file($path)) {
    throw new RuntimeException('PHAR build produced no artifact.');
}
chmod($path, 0755);
