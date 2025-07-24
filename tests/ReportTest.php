<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase
{
    private $script_path = __DIR__ . '/../report.php';
    private $now;

    protected function setUp(): void
    {
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }

        $this->now = new DateTimeImmutable('today');
    }

    private function runReportScript(string $date): string
    {
        global $argv;
        $argv = ['report.php', $date];

        ob_start();
        include $this->script_path;
        return ob_get_clean();
    }

    public function testReportOutputIsDeterministic()
    {
        // Setup for this specific test
        save_xml_file(TASKS_DIR . '/normal.xml', new SimpleXMLElement('<task><name>Normal Report Task</name></task>'));
        $completed_normal_xml = new SimpleXMLElement('<task><name>Completed Normal Task</name><history><entry>2025-01-01</entry></history></task>');
        save_xml_file(TASKS_DIR . '/normal_completed.xml', $completed_normal_xml);
        $due_today_date = $this->now->format('Y-m-d');
        save_xml_file(TASKS_DIR . '/due_today.xml', new SimpleXMLElement('<task><name>Due Today Task</name><due>' . $due_today_date . '</due></task>'));
        $overdue_date = $this->now->modify('-8 days')->format('Y-m-d');
        save_xml_file(TASKS_DIR . '/overdue.xml', new SimpleXMLElement('<task><name>Overdue Task</name><due>' . $overdue_date . '</due></task>'));
        $upcoming_date = $this->now->modify('+3 days')->format('Y-m-d');
        save_xml_file(TASKS_DIR . '/upcoming.xml', new SimpleXMLElement('<task><name>Upcoming Task</name><due>' . $upcoming_date . '</due><preview>5</preview></task>'));
        $future_date = $this->now->modify('+10 days')->format('Y-m-d');
        save_xml_file(TASKS_DIR . '/future.xml', new SimpleXMLElement('<task><name>Future Task</name><due>' . $future_date . '</due><preview>5</preview></task>'));
        $rec_overdue_completed = $this->now->modify('-19 days')->format('Y-m-d');
        $xml_rec_over = new SimpleXMLElement('<task><name>Recurring Overdue</name><recurring><completed>' . $rec_overdue_completed . '</completed><duration>14</duration></recurring></task>');
        save_xml_file(TASKS_DIR . '/rec_overdue.xml', $xml_rec_over);
        $rec_upcoming_completed = $this->now->modify('-5 days')->format('Y-m-d');
        $xml_rec_up = new SimpleXMLElement('<task><name>Recurring Upcoming No Preview</name><recurring><completed>' . $rec_upcoming_completed . '</completed><duration>7</duration></recurring></task>');
        save_xml_file(TASKS_DIR . '/rec_upcoming.xml', $xml_rec_up); // Due in 2 days

        // Run the report for the mocked "today"
        $output = $this->runReportScript($this->now->format('Y-m-d'));

        // Things that SHOULD be in the report
        $this->assertStringContainsString('Normal Report Task', $output);
        $this->assertStringContainsString('Due Today Task', $output);
        $this->assertStringContainsString('Overdue Task (was due 8 days ago)', $output);
        $this->assertStringContainsString('Upcoming Task (due in 3 days)', $output);
        $this->assertStringContainsString('Recurring Overdue (was due 5 days ago)', $output);

        // Things that should NOT be in the report
        $this->assertStringNotContainsString('Future Task', $output);
        $this->assertStringNotContainsString('Recurring Upcoming No Preview', $output);
        $this->assertStringNotContainsString('Completed Normal Task', $output); // New check
    }

    public function testReportWithPreviewForRecurring()
    {
        // Add a recurring task that will be upcoming with a preview
        // Completed 5 days ago, recurs every 7 days -> due in 2 days. Preview is 5 days.
        $completed_date = $this->now->modify('-5 days')->format('Y-m-d');
        $xml = new SimpleXMLElement('<task><name>Recurring With Preview</name><recurring><completed>' . $completed_date . '</completed><duration>7</duration></recurring><preview>5</preview></task>');
        save_xml_file(TASKS_DIR . '/rec_preview.xml', $xml);

        $output = $this->runReportScript($this->now->format('Y-m-d'));

        $this->assertStringContainsString('Recurring With Preview (due in 2 days)', $output);
    }

    public function testReportWarnsAboutInvalidFiles()
    {
        // Create a malformed XML file
        file_put_contents(TASKS_DIR . '/malformed.xml', '<task><name>Malformed Task</name>');

        // Create an XML file that does not conform to the schema
        file_put_contents(TASKS_DIR . '/non-conforming.xml', '<?xml version="1.0"?><badroot></badroot>');

        $output = $this->runReportScript($this->now->format('Y-m-d'));

        // Check for the warning message
        $this->assertStringContainsString('The following task files are invalid or corrupt and were skipped', $output);

        // Check that the invalid files are listed
        $this->assertStringContainsString('- malformed.xml', $output);
        $this->assertStringContainsString('- non-conforming.xml', $output);

        // Check that the task from the malformed file was not included in the report
        $this->assertStringNotContainsString('Malformed Task', $output);
    }

    public function testReportSortingByStatusThenPriority()
    {
        // Overdue tasks
        save_xml_file(TASKS_DIR . '/overdue_low.xml', new SimpleXMLElement('<task><name>Overdue Low Prio</name><due>' . $this->now->modify('-3 days')->format('Y-m-d') . '</due><priority>-1</priority></task>'));
        save_xml_file(TASKS_DIR . '/overdue_high.xml', new SimpleXMLElement('<task><name>Overdue High Prio</name><due>' . $this->now->modify('-1 day')->format('Y-m-d') . '</due><priority>5</priority></task>'));

        // Due today tasks
        save_xml_file(TASKS_DIR . '/today_high.xml', new SimpleXMLElement('<task><name>Today High Prio</name><due>' . $this->now->format('Y-m-d') . '</due><priority>10</priority></task>'));
        save_xml_file(TASKS_DIR . '/today_normal.xml', new SimpleXMLElement('<task><name>Today Normal Prio</name><due>' . $this->now->format('Y-m-d') . '</due></task>')); // Default priority 0

        // Upcoming tasks
        save_xml_file(TASKS_DIR . '/upcoming_medium.xml', new SimpleXMLElement('<task><name>Upcoming Medium Prio</name><due>' . $this->now->modify('+2 days')->format('Y-m-d') . '</due><preview>3</preview><priority>2</priority></task>'));
        save_xml_file(TASKS_DIR . '/upcoming_low.xml', new SimpleXMLElement('<task><name>Upcoming Low Prio</name><due>' . $this->now->modify('+1 day')->format('Y-m-d') . '</due><preview>3</preview><priority>-5</priority></task>'));


        $output = $this->runReportScript($this->now->format('Y-m-d'));

        // Remove color codes for easier comparison
        $output_no_color = preg_replace('/\e\[[0-9;]*m/', '', $output);

        // Expected order:
        // 1. Overdue High Prio (prio 5)
        // 2. Overdue Low Prio (prio -1)
        // 3. Today High Prio (prio 10)
        // 4. Today Normal Prio (prio 0)
        // 5. Upcoming Medium Prio (prio 2)
        // 6. Upcoming Low Prio (prio -5)
        $expected_order = [
            'OVERDUE: Overdue High Prio',
            'OVERDUE: Overdue Low Prio',
            'DUE TODAY: Today High Prio',
            'DUE TODAY: Today Normal Prio',
            'UPCOMING: Upcoming Medium Prio',
            'UPCOMING: Upcoming Low Prio'
        ];

        // Create a regex that looks for the task names in the specified order, ignoring the details in parentheses
        $pattern_parts = array_map(function ($name) {
            return preg_quote($name, '/');
        }, $expected_order);
        $pattern = '/' . implode('.*?', $pattern_parts) . '/s';

        $this->assertMatchesRegularExpression($pattern, $output_no_color, "Report output is not sorted correctly by status and then priority.");
    }
}
