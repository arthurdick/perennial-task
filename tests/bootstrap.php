<?php

// tests/bootstrap.php

// Define a constant to signal to the application that we are in a testing environment.
// This must be defined BEFORE any application files are included.
define('PERENNIAL_TASK_TESTING', true);

// Set up the test environment

// Error reporting
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('UTC');

// Define constants for the test environment
define('TESTS_TEMP_DIR', __DIR__ . '/temp');
define('TASKS_DIR', TESTS_TEMP_DIR . '/tasks');
define('COMPLETIONS_LOG', TESTS_TEMP_DIR . '/completions.log');
define('XSD_PATH', realpath(__DIR__ . '/../task.xsd'));
define('TASKS_PER_PAGE', 5);

// Create temporary directories for testing
if (!is_dir(TESTS_TEMP_DIR)) {
    mkdir(TESTS_TEMP_DIR, 0777, true);
}
if (!is_dir(TASKS_DIR)) {
    mkdir(TASKS_DIR, 0777, true);
}

// Clean up temp directory before tests
function clean_temp_dir() {
    if (!is_dir(TASKS_DIR)) return;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(TASKS_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    if (file_exists(COMPLETIONS_LOG)) {
        unlink(COMPLETIONS_LOG);
    }
}

// Autoloader for PHPUnit
require_once __DIR__ . '/../vendor/autoload.php';

// Include the common functions file
require_once __DIR__ . '/../common.php';

// Clean up before running tests
clean_temp_dir();

// Clean up after tests are done
register_shutdown_function(function () {
    // clean_temp_dir();
    // rmdir(TASKS_DIR);
    // rmdir(TESTS_TEMP_DIR);
});

