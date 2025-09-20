<?php

declare(strict_types=1);

// tests/bootstrap.php

// Define a constant to signal to the application that we are in a testing environment.
// This must be defined BEFORE any application files are included.
define('PERENNIAL_TASK_TESTING', true);

// Set up the test environment

// Error reporting
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('UTC');

// Define constants for the test environment that are NOT handled by the config loader
define('TESTS_TEMP_DIR', __DIR__ . '/temp');

// Set environment variables to control the configuration during tests
// This ensures the test environment uses its own temporary directories.
putenv('PERENNIAL_TASKS_DIR=' . TESTS_TEMP_DIR . '/tasks');
putenv('PERENNIAL_COMPLETIONS_LOG=' . TESTS_TEMP_DIR . '/completions.log');
putenv('PERENNIAL_XSD_PATH=' . realpath(__DIR__ . '/../task.xsd'));
putenv('PERENNIAL_TASKS_PER_PAGE=5');
// We don't set PERENNIAL_SAVE_HISTORY here, allowing the app's default (true)
// to take effect unless overridden by a specific test.

// Create temporary directories for testing
if (!is_dir(TESTS_TEMP_DIR)) {
    mkdir(TESTS_TEMP_DIR, 0777, true);
}
$tasks_dir_from_env = getenv('PERENNIAL_TASKS_DIR');
if (!is_dir($tasks_dir_from_env)) {
    mkdir($tasks_dir_from_env, 0777, true);
}


// Clean up temp directory before tests
function clean_temp_dir()
{
    $tasks_dir = getenv('PERENNIAL_TASKS_DIR');
    if (!$tasks_dir || !is_dir($tasks_dir)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tasks_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    $completions_log = getenv('PERENNIAL_COMPLETIONS_LOG');
    if ($completions_log && file_exists($completions_log)) {
        unlink($completions_log);
    }
}

// Autoloader for PHPUnit
require_once __DIR__ . '/../vendor/autoload.php';

// Include the common functions file. This will now also trigger the config initialization.
require_once __DIR__ . '/../common.php';

// The unconditional cleanup call has been removed from here.
// Test classes will now handle cleanup in their setUp() methods.

// Clean up after tests are done
register_shutdown_function(function () {
    // clean_temp_dir();
    // rmdir(getenv('PERENNIAL_TASKS_DIR'));
    // rmdir(TESTS_TEMP_DIR);
});
