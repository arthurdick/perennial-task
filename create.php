<?php

declare(strict_types=1);

require_once 'common.php';

// 1. Define all possible long options for the 'create' command.
$long_options = [
    "name:",
    "due:",
    "preview:",
    "reschedule-interval:",
    "reschedule-from:",
    "priority:",
];

// 2. Use the new manual parser.
$cli_args = parse_argv_manual($argv, $long_options);
$options = $cli_args['options'];

// Non-Interactive Mode: Triggered if any options are present.
if (!empty($options)) {
    echo "--- Creating New Task (Non-Interactive) ---\n";

    // The --name flag is required for non-interactive use.
    if (!isset($options['name']) || empty(trim($options['name']))) {
        file_put_contents('php://stderr', "Error: --name is required for non-interactive creation and cannot be empty.\n");
        exit(1);
    }
    $name = trim($options['name']);

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
        if (isset($options['reschedule-interval']) || isset($options['reschedule-from'])) {
            if (!isset($options['reschedule-interval'], $options['reschedule-from'])) {
                file_put_contents('php://stderr', "Error: Both --reschedule-interval and --reschedule-from must be provided to create a rescheduling task.\n");
                exit(1);
            }
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
        if (isset($options['preview'])) {
            if (!ctype_digit($options['preview']) || $options['preview'] < 0) {
                file_put_contents('php://stderr', "Error: --preview must be a non-negative integer.\n");
                exit(1);
            }
            $xml->addChild('preview', $options['preview']);
        }
    } elseif (isset($options['reschedule-interval']) || isset($options['reschedule-from']) || isset($options['preview'])) {
        file_put_contents('php://stderr', "Error: --due is required when using any other scheduling options like --reschedule-interval, --reschedule-from, or --preview.\n");
        exit(1);
    }

    // Add priority if specified
    if (isset($options['priority'])) {
        if (filter_var($options['priority'], FILTER_VALIDATE_INT) === false) {
            file_put_contents('php://stderr', "Error: --priority must be an integer.\n");
            exit(1);
        }
        $xml->addChild('priority', $options['priority']);
    }

} else {
    // Interactive Mode: Fallback if no options are used.
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
            $xml->addChild('preview', strval($preview));
        }
    }

    $priority = get_optional_integer_input("Enter priority (e.g., -1, 0, 1), press Enter for default (0): ");
    if ($priority !== null) {
        $xml->addChild('priority', strval($priority));
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
