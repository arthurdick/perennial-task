<?php

declare(strict_types=1);

require_once 'common.php';

// --- Main Script Execution ---

echo "--- Task Completion History ---\n";

// 1. This script has no specific options to define.
$long_options = [];

// 2. Use the manual parser to find a potential filepath.
$cli_args = parse_argv_manual($argv, $long_options);
$filepath = $cli_args['filepath'];

// 3. If no filepath was provided on the command line, fall back to the interactive selector.
if ($filepath === null) {
    $filepath = select_task_file($argv, $long_options, 'history', 'all');
}

// 4. If no file was selected, exit gracefully.
if ($filepath === null) {
    exit(0);
}

// Load the validated XML file.
$xml = simplexml_load_file($filepath);

echo "History for task: " . (string)$xml->name . "\n";

if (isset($xml->history) && $xml->history->count() > 0) {
    echo "------------------------\n";
    foreach ($xml->history->entry as $entry) {
        echo "- " . (string)$entry . "\n";
    }
    echo "------------------------\n";
} else {
    echo "No completion history found for this task.\n";
}
