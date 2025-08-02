<?php

// build.php

$pharFile = 'prn.phar';

// Clean up any old phar
if (file_exists($pharFile)) {
    unlink($pharFile);
}

$p = new Phar($pharFile);

// Start buffering. This is essential for setting the stub later.
$p->startBuffering();

// Add runtime files from the project root
$root_files = [
    'prn',
    'common.php',
    'config.php',
    'create.php',
    'describe.php',
    'edit.php',
    'complete.php',
    'history.php',
    'report.php',
    'task.xsd',
    'LICENSE'
];

foreach ($root_files as $file) {
    if (file_exists($file)) {
        $p->addFile($file, $file);
    } else {
        echo "Warning: File not found and not added to PHAR: $file\n";
    }
}

// Add production vendor dependencies
if (is_dir(__DIR__ . '/vendor')) {
    $p->buildFromIterator(
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__ . '/vendor', FilesystemIterator::SKIP_DOTS)
        ),
        __DIR__
    );
}

// Create the default stub to run the bin script.
// It's important to use a shebang for command-line execution.
$stub = "#!/usr/bin/env php \n" . $p->createDefaultStub('prn');
$p->setStub($stub);

// Stop buffering and write changes to disk.
$p->stopBuffering();

// Make the file executable
chmod($pharFile, 0755);

echo "✅ Successfully built {$pharFile}\n";
