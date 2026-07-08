<?php

declare(strict_types=1);

if ('1' === ini_get('phar.readonly')) {
    fwrite(STDERR, "Set phar.readonly=0 to build the release artifact.\n");
    exit(1);
}

@mkdir(__DIR__.'/../dist', 0777, true);
$root = dirname(__DIR__);
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
$phar->setStub("#!/usr/bin/env php\n".Phar::createDefaultStub('bin/yaup'));
$phar->stopBuffering();
if (0 === $count || !is_file($path)) {
    throw new RuntimeException('PHAR build produced no artifact.');
}
chmod($path, 0755);
