<?php

require_once 'common.php';

// --- Argument Parsing ---

// Use getopt to parse command-line flags. This is more robust than manual parsing.
$options = getopt('', [
    "name:",              // Required: Task name
    "due:",               // Optional: Due date (YYYY-MM-DD)
    "preview:",           // Optional: Preview days (integer)
    "reschedule-interval:", // Optional: e.g., "1 month", "14 days"
    "reschedule-from:"    // Optional: "due_date" or "completion_date"
]);

// --- Main Script Execution ---

// Non-Interactive Mode: Triggered if the --name flag is present.
if (isset($options['name'])) {
    $name = trim($options['name']);
    if (empty($name)) {
        file_put_contents('php://stderr', "Error: --name cannot be empty.\n");
        exit(1);
    }

    $xml = new SimpleXMLElement('<task></task>');
    $xml->addChild('name', htmlspecialchars($name));

    // Check if task should be scheduled
    if (isset($options['due'])) {
        if (!validate_date($options['due'])) {
            file_put_contents('php://stderr', "Error: Invalid format for --due. Use YYYY-MM-DD.\n");
            exit(1);
        }
        $xml->addChild('due', $options['due']);

        // Add reschedule logic if specified
        if (isset($options['reschedule-interval']) && isset($options['reschedule-from'])) {
            $from = $options['reschedule-from'];
            if (!in_array($from, ['due_date', 'completion_date'])) {
                file_put_contents('php://stderr', "Error: --reschedule-from must be 'due_date' or 'completion_date'.\n");
                exit(1);
            }
            $xml->addChild('reschedule');
            $xml->reschedule->addChild('interval', $options['reschedule-interval']);
            $xml->reschedule->addChild('from', $from);
        }

        // Add preview days if specified
        if (isset($options['preview']) && ctype_digit($options['preview'])) {
            $xml->addChild('preview', $options['preview']);
        }
    }

    echo "--- Creating New Task (Non-Interactive) ---\n";
} else {
    // Interactive Mode: Fallback to original behavior if --name is not used.
    echo "--- Create a New Task ---\n";

    if (!is_dir(TASKS_DIR)) {
        if (!mkdir(TASKS_DIR, 0755, true)) {
            echo "Error: Could not create tasks directory.\n";
            exit(1);
        }
    }

    $name = '';
    while (empty(trim($name))) {
        $name = prompt_user("Enter the task name: ");
        if (empty(trim($name))) {
            echo "Task name cannot be empty.\n";
        }
    }

    $type_options = [
        'n' => 'Normal (a simple, one-off task)',
        's' => 'Scheduled (a task with a due date that may repeat)'
    ];
    $type_choice = get_menu_choice("Select task type:", $type_options);

    $xml = new SimpleXMLElement('<task></task>');
    $xml->addChild('name', htmlspecialchars($name));

    if ($type_choice === 's') {
        collect_scheduled_task_details($xml);
        $preview = get_optional_positive_integer_input("Preview days in advance? (optional, press Enter to skip): ");
        if ($preview !== null && $preview > 0) {
            $xml->addChild('preview', $preview);
        }
    }
}

// --- File Saving (Common to both modes) ---

$base_filename = sanitize_filename($name);
$filepath = TASKS_DIR . '/' . $base_filename . '.xml';
$counter = 1;
while (file_exists($filepath)) {
    $filepath = TASKS_DIR . '/' . $base_filename . '_' . $counter . '.xml';
    $counter++;
}

if (save_xml_file($filepath, $xml)) {
    echo "\nSuccess! Task file created at: $filepath\n";
} else {
    echo "\nError! Could not save the task file.\n";
    exit(1);
}
