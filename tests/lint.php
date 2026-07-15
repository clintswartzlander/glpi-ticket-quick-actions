<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = ['src', 'front', 'tests', 'scripts'];
$files = [$root . '/setup.php', $root . '/hook.php'];

foreach ($directories as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);
$failed = false;
foreach ($files as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        $failed = true;
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
    }
    $output = [];
}

if ($failed) {
    exit(1);
}

echo sprintf("Linted %d PHP files successfully.%s", count($files), PHP_EOL);
