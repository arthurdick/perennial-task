<?php

require_once 'common.php';

// --- Function Definitions ---

if (!function_exists('describe_recurring_task')) {
    /**
     * Describes a recurring task in detail.
     *
     * @param SimpleXMLElement $task The XML object for the task.
     */
    function describe_recurring_task(SimpleXMLElement $task): void
    {
        $name = (string)$task->name;
        $recur_duration = (int)$task->recurring->duration;
        $completed_date_str = (string)$task->recurring->completed;
        
        $now = new DateTime('today');
        $completed_date = new DateTime($completed_date_str);
        $interval = $completed_date->diff($now);
        
        $days_since_completed = $interval->days;
        // If the completion date is in the future, it's not logical, but we'll show it as negative.
        if ($interval->invert) {
             $days_since_completed *= -1;
        }

        echo "Task: $name\n";
        echo "Type: Recurring\n";
        echo "Details: Repeats every $recur_duration " . pluralize_days($recur_duration) . ".\n";
        
        if ($days_since_completed >= 0) {
            echo "Status: Last completed on $completed_date_str (" . $days_since_completed . " " . pluralize_days($days_since_completed) . " ago).\n";
        } else {
             echo "Status: Last completed date is in the future ($completed_date_str).\n";
        }
    }
}

if (!function_exists('describe_due_task')) {
    /**
     * Describes a task with a specific due date in detail.
     *
     * @param SimpleXMLElement $task The XML object for the task.
     */
    function describe_due_task(SimpleXMLElement $task): void
    {
        $name = (string)$task->name;
        $due_date_str = (string)$task->due;
        $preview_duration = isset($task->preview) ? (int)$task->preview : 0;

        $now = new DateTime('today');
        $due_date = new DateTime($due_date_str);
        $due_interval = $now->diff($due_date);
        
        $days_until_due = $due_interval->days;
        
        echo "Task: $name\n";
        echo "Type: Due Date\n";
        echo "Details: Due on $due_date_str.\n";
        
        // Describe the due status
        if ($due_interval->invert) {
            echo "Status: Overdue by $days_until_due " . pluralize_days($days_until_due) . ".\n";
        } elseif ($days_until_due === 0) {
            echo "Status: Due today.\n";
        } else {
            echo "Status: Due in $days_until_due " . pluralize_days($days_until_due) . ".\n";
        }
        
        // Describe the preview/display status
        if ($preview_duration > 0) {
            $display_date = (clone $due_date)->modify("-$preview_duration days");
            $display_interval = $now->diff($display_date);
            $days_until_display = $display_interval->days;

            echo "Preview: Set to display $preview_duration " . pluralize_days($preview_duration) . " in advance (on " . $display_date->format('Y-m-d') . ").\n";
            
            if ($display_interval->invert) {
                 echo "Display Status: Is currently being displayed (for the last $days_until_display " . pluralize_days($days_until_display) . ").\n";
            } elseif ($days_until_display === 0) {
                 echo "Display Status: Starts displaying today.\n";
            } else {
                 echo "Display Status: Will be displayed in $days_until_display " . pluralize_days($days_until_display) . ".\n";
            }
        }
    }
}

if (!function_exists('describe_normal_task')) {
    /**
     * Describes a normal task.
     *
     * @param SimpleXMLElement $task The XML object for the task.
     */
    function describe_normal_task(SimpleXMLElement $task): void
    {
        $name = (string)$task->name;
        echo "Task: $name\n";
        echo "Type: Normal\n";
        echo "Details: This is a simple, one-off task.\n";
    }
}

// --- Main Script Execution ---

echo "--- Describe a Task ---\n";

// Use the shared function to select a task file, either by argument or interactively.
$filepath = select_task_file($argv, 'describe');

// If no file was selected or found, exit gracefully.
if ($filepath === null) {
    exit(0);
}

// Load the validated XML file.
$xml = simplexml_load_file($filepath);

// Use the shared function to determine the task type.
$type = get_task_type($xml);

// Call the appropriate function based on the type.
switch ($type) {
    case 'recurring':
        describe_recurring_task($xml);
        break;
    case 'due':
        describe_due_task($xml);
        break;
    case 'normal':
        describe_normal_task($xml);
        break;
}

