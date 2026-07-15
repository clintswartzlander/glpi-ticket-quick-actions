<?php

declare(strict_types=1);

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required to build a release.\n");
    exit(1);
}

$root = dirname(__DIR__);
$version = 'unknown';
$setup = file_get_contents($root . '/setup.php');
if (preg_match("/PLUGIN_QUICKACTIONS_VERSION',\s*'([^']+)'/", $setup, $matches) === 1) {
    $version = $matches[1];
}

$buildDirectory = $root . '/build';
if (!is_dir($buildDirectory) && !mkdir($buildDirectory, 0775, true) && !is_dir($buildDirectory)) {
    throw new RuntimeException('Unable to create the build directory.');
}

$archivePath = $buildDirectory . '/quickactions-' . $version . '.zip';
$zip = new ZipArchive();
if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Unable to create the release archive.');
}

$excludedTopLevel = ['.git', '.github', 'build', 'tests'];
$excludedFiles = ['.gitignore', 'composer.lock'];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $topLevel = explode('/', $relative, 2)[0];
    if (in_array($topLevel, $excludedTopLevel, true) || in_array($relative, $excludedFiles, true)) {
        continue;
    }
    $zip->addFile($file->getPathname(), 'quickactions/' . $relative);
}

$zip->close();
echo $archivePath . PHP_EOL;
