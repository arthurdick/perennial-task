<?php

declare(strict_types=1);

require_once 'common.php';

echo "--- Purge Task History ---\n";

// 1. Define all possible long options for the 'purge' command.
$long_options = [
    "force",
];

// 2. Use the manual parser to get options and a potential filepath.
$cli_args = parse_argv_manual($argv, $long_options);
$parsed_options = $cli_args['options'];
$filepath = $cli_args['filepath'];

$is_forced = array_key_exists('force', $parsed_options);

if ($filepath !== null) {
    // Purge a single task
    $xml = simplexml_load_file($filepath);
    if (isset($xml->history)) {
        unset($xml->history);
        if (save_xml_file($filepath, $xml)) {
            echo "History purged for task: " . (string)$xml->name . "\n";
        } else {
            file_put_contents('php://stderr', "Error: Could not save the updated task file for '" . (string)$xml->name . "'.\n");
        }
    } else {
        echo "No history found for task: " . (string)$xml->name . "\n";
    }
} else {
    // Purge all tasks
    if (!$is_forced) {
        if (!get_yes_no_input("Are you sure you want to purge the history of ALL tasks? This cannot be undone. (y/N): ", 'n')) {
            echo "Operation cancelled.\n";
            exit(0);
        }
    }

    $files = glob(TASKS_DIR . '/*.xml');
    if (empty($files)) {
        echo "No tasks found.\n";
        exit(0);
    }

    $purged_count = 0;
    foreach ($files as $file) {
        if (!validate_task_file($file, true)) {
            file_put_contents('php://stderr', "Warning: Skipping invalid task file: " . basename($file) . "\n");
            continue;
        }

        $xml = simplexml_load_file($file);
        if (isset($xml->history)) {
            unset($xml->history);
            if (save_xml_file($file, $xml)) {
                $purged_count++;
            } else {
                file_put_contents('php://stderr', "Error: Could not save the updated task file for '" . (string)$xml->name . "'.\n");
            }
        }
    }
    echo "History purged for $purged_count " . pluralize($purged_count, 'task', 'tasks') . ".\n";
}
