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
        $this->assertStringContainsString('Details: This is a simple, one-off task.', $output);
        $this->assertStringContainsString('Status: Not yet completed.', $output);
        $this->assertStringNotContainsString('History:', $output);
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
        $this->assertStringContainsString('Type: Scheduled', $output_future);
        $this->assertStringContainsString('Due on ' . $due_date_future->format('Y-m-d'), $output_future);
        $this->assertStringContainsString('Status: Due in 10 days.', $output_future);
        $this->assertStringContainsString('Preview: Set to display 5 days in advance', $output_future);
        $this->assertStringContainsString('Display Status: Will be displayed in 5 days.', $output_future);
        $this->assertStringNotContainsString('History:', $output_future);

        // Overdue task
        $due_date_overdue = $now->modify('-8 days');
        $xml_overdue = new SimpleXMLElement('<task><name>Overdue Task</name><due>' . $due_date_overdue->format('Y-m-d') . '</due></task>');
        $filepath_overdue = TASKS_DIR . '/due_overdue.xml';
        save_xml_file($filepath_overdue, $xml_overdue);
        $output_overdue = $this->runDescribeScript($filepath_overdue);
        $this->assertStringContainsString('Task: Overdue Task', $output_overdue);
        $this->assertStringContainsString('Status: Overdue by 8 days.', $output_overdue);
        $this->assertStringNotContainsString('History:', $output_overdue);

        // Due today
        $xml_today = new SimpleXMLElement('<task><name>Due Today Task</name><due>' . $now->format('Y-m-d') . '</due></task>');
        $filepath_today = TASKS_DIR . '/due_today.xml';
        save_xml_file($filepath_today, $xml_today);
        $output_today = $this->runDescribeScript($filepath_today);
        $this->assertStringContainsString('Task: Due Today Task', $output_today);
        $this->assertStringContainsString('Status: Due today.', $output_today);
        $this->assertStringNotContainsString('History:', $output_today);
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
        $this->assertStringContainsString('Type: Scheduled', $output);
        $this->assertStringContainsString('(Legacy Format) Repeats every 10 days from completion.', $output);
        $this->assertStringContainsString('Status: Due in 3 days.', $output);
        $this->assertStringNotContainsString('History:', $output);
    }

    public function testDescribeTaskWithHistory()
    {
        $xml = new SimpleXMLElement('<task><name>A Task With History</name><history><entry>2025-01-01</entry><entry>2025-01-15</entry></history></task>');
        $filepath = TASKS_DIR . '/history.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runDescribeScript($filepath);

        $this->assertStringNotContainsString('--- Completion History ---', $output);
        $this->assertStringNotContainsString('- 2025-01-01', $output);
        $this->assertStringContainsString('Status: Completed.', $output);
        $this->assertStringContainsString('History: 2 completions logged.', $output);
    }

    public function testDescribeTaskWithoutHistory()
    {
        $xml = new SimpleXMLElement('<task><name>A Task Without History</name></task>');
        $filepath = TASKS_DIR . '/no_history.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runDescribeScript($filepath);

        $this->assertStringNotContainsString('--- Completion History ---', $output);
        $this->assertStringNotContainsString('History:', $output);
    }
}
