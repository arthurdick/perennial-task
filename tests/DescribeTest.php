<?php

use PHPUnit\Framework\TestCase;

class DescribeTest extends TestCase
{
    private $script_path = __DIR__ . '/../describe.php';

    protected function setUp(): void
    {
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }
    }

    private function runDescribeScript(string $task_filepath): string
    {
        global $argv;
        $argv = ['describe.php', $task_filepath];

        ob_start();
        // Use `include` instead of `require_once`. This ensures the script's main logic
        // is executed for every test, while the `function_exists` guards inside
        // describe.php prevent fatal "cannot redeclare function" errors.
        include $this->script_path;
        return ob_get_clean();
    }

    public function testDescribeNormalTask()
    {
        $xml = new SimpleXMLElement('<task><name>A Normal Task</name></task>');
        $filepath = TASKS_DIR . '/normal.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runDescribeScript($filepath);

        $this->assertStringContainsString('Task: A Normal Task', $output);
        $this->assertStringContainsString('Type: Normal', $output);
        $this->assertStringContainsString('This is a simple, one-off task', $output);
    }

    public function testDescribeDueTask()
    {
        $now = new DateTimeImmutable('today');

        // Due in the future
        $due_date_future = $now->modify('+10 days');
        $xml_future = new SimpleXMLElement('<task><name>Future Due Task</name><due>' . $due_date_future->format('Y-m-d') . '</due><preview>5</preview></task>');
        $filepath_future = TASKS_DIR . '/due_future.xml';
        save_xml_file($filepath_future, $xml_future);
        
        $output_future = $this->runDescribeScript($filepath_future);
        $this->assertStringContainsString('Task: Future Due Task', $output_future);
        $this->assertStringContainsString('Type: Due Date', $output_future);
        $this->assertStringContainsString('Due on ' . $due_date_future->format('Y-m-d'), $output_future);
        $this->assertStringContainsString('Status: Due in 10 days.', $output_future);
        $this->assertStringContainsString('Preview: Set to display 5 days in advance', $output_future);
        $this->assertStringContainsString('Display Status: Will be displayed in 5 days.', $output_future);

        // Overdue task
        $due_date_overdue = $now->modify('-8 days');
        $xml_overdue = new SimpleXMLElement('<task><name>Overdue Task</name><due>' . $due_date_overdue->format('Y-m-d') . '</due></task>');
        $filepath_overdue = TASKS_DIR . '/due_overdue.xml';
        save_xml_file($filepath_overdue, $xml_overdue);
        $output_overdue = $this->runDescribeScript($filepath_overdue);
        $this->assertStringContainsString('Task: Overdue Task', $output_overdue);
        $this->assertStringContainsString('Status: Overdue by 8 days.', $output_overdue);
        
        // Due today
        $xml_today = new SimpleXMLElement('<task><name>Due Today Task</name><due>' . $now->format('Y-m-d') . '</due></task>');
        $filepath_today = TASKS_DIR . '/due_today.xml';
        save_xml_file($filepath_today, $xml_today);
        $output_today = $this->runDescribeScript($filepath_today);
        $this->assertStringContainsString('Task: Due Today Task', $output_today);
        $this->assertStringContainsString('Status: Due today.', $output_today);
    }
    
    public function testDescribeRecurringTask()
    {
        $now = new DateTimeImmutable('today');
        $completed_date = $now->modify('-7 days');

        $xml = new SimpleXMLElement('<task><name>Weekly Recurring</name><recurring><completed>' . $completed_date->format('Y-m-d') . '</completed><duration>10</duration></recurring></task>');
        $filepath = TASKS_DIR . '/recurring.xml';
        save_xml_file($filepath, $xml);
        
        $output = $this->runDescribeScript($filepath);
        
        $this->assertStringContainsString('Task: Weekly Recurring', $output);
        $this->assertStringContainsString('Type: Recurring', $output);
        $this->assertStringContainsString('Repeats every 10 days.', $output);
        $this->assertStringContainsString('Status: Last completed on ' . $completed_date->format('Y-m-d') . ' (7 days ago).', $output);
    }
}

