<?php

require_once 'common.php';

// --- Main Script Execution ---

echo "--- Task Completion History ---\n";

// Use the shared function to select a task file, either by argument or interactively.
$filepath = select_task_file($argv, 'history', 'all');

// If no file was selected or found, exit gracefully.
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
