<?php

use PHPUnit\Framework\TestCase;

class CompleteTest extends TestCase
{
    private $script_path = __DIR__ . '/../complete.php';

    protected function setUp(): void
    {
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }
    }

    private function runCompleteScript(string $task_filepath, array $inputs = []): string
    {
        global $argv;
        $argv = ['complete.php', $task_filepath];

        $input_stream = fopen('php://memory', 'r+');
        foreach ($inputs as $input) {
            fwrite($input_stream, $input . PHP_EOL);
        }
        rewind($input_stream);

        $GLOBALS['__MOCK_PROMPT_USER_FUNC'] = function (string $prompt) use ($input_stream): string {
            $line = fgets($input_stream);
            return $line !== false ? trim($line) : '';
        };

        ob_start();
        include $this->script_path;
        $output = ob_get_clean();

        unset($GLOBALS['__MOCK_PROMPT_USER_FUNC']);
        fclose($input_stream);

        return $output;
    }

    public function testCompleteNormalTask()
    {
        $xml = new SimpleXMLElement('<task><name>Normal Task To Complete</name></task>');
        $filepath = TASKS_DIR . '/normal_complete.xml';
        save_xml_file($filepath, $xml);

        $this->assertFileExists($filepath);

        $output = $this->runCompleteScript($filepath, ['2025-07-15']);

        $this->assertStringContainsString("Task 'Normal Task To Complete' has been marked as complete on 2025-07-15.", $output);
        $this->assertFileExists($filepath);

        $updated_xml = simplexml_load_file($filepath);
        $this->assertTrue(isset($updated_xml->history));
        $this->assertEquals('2025-07-15', (string)$updated_xml->history->entry);
    }

    public function testCompleteOneOffScheduledTaskCleansUpTags()
    {
        $xml = new SimpleXMLElement('<task>
            <name>One-off Project</name>
            <due>2025-08-15</due>
            <preview>10</preview>
        </task>');
        $filepath = TASKS_DIR . '/one_off.xml';
        save_xml_file($filepath, $xml);

        // Inputs: completion date, 'y' to remove due date
        $this->runCompleteScript($filepath, ['2025-08-10', 'y']);

        $updated_xml = simplexml_load_file($filepath);

        // Assert that both due and preview tags are removed
        $this->assertFalse(isset($updated_xml->due));
        $this->assertFalse(isset($updated_xml->preview));
        $this->assertTrue(isset($updated_xml->history));
    }

    public function testCompleteScheduledTask_RescheduleFromDueDate()
    {
        $xml = new SimpleXMLElement('<task>
            <name>Pay Rent</name>
            <due>2025-08-01</due>
            <reschedule><interval>1 month</interval><from>due_date</from></reschedule>
        </task>');
        $filepath = TASKS_DIR . '/rent.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runCompleteScript($filepath, ['2025-08-01']);
        $this->assertStringContainsString("Task has been rescheduled to 2025-09-01", $output);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('2025-09-01', (string)$updated_xml->due);
        $this->assertEquals('2025-08-01', (string)$updated_xml->history->entry);
    }

    public function testCompleteScheduledTask_RescheduleFromCompletionDate()
    {
        $xml = new SimpleXMLElement('<task>
            <name>Water Plants</name>
            <due>2025-07-18</due>
            <reschedule><interval>3 days</interval><from>completion_date</from></reschedule>
        </task>');
        $filepath = TASKS_DIR . '/plants.xml';
        save_xml_file($filepath, $xml);

        // Complete it 2 days late
        $output = $this->runCompleteScript($filepath, ['2025-07-20']);
        // New due date should be 2025-07-23 (completion + 3 days)
        $this->assertStringContainsString("Task has been rescheduled to 2025-07-23", $output);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('2025-07-23', (string)$updated_xml->due);
        $this->assertEquals('2025-07-20', (string)$updated_xml->history->entry);
    }

    public function testMigrationOfLegacyRecurringTask()
    {
        // This task format will be migrated on completion
        $xml = new SimpleXMLElement('<task>
            <name>Old Recurring Task</name>
            <recurring><completed>2025-07-01</completed><duration>7</duration></recurring>
        </task>');
        $filepath = TASKS_DIR . '/legacy_recurring.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runCompleteScript($filepath, ['2025-07-08']);
        $this->assertStringContainsString("Migrated task from old 'recurring' format", $output);
        $this->assertStringContainsString("Task has been rescheduled to 2025-07-15", $output); // 7 days from completion
        $updated_xml = simplexml_load_file($filepath);
        $this->assertFalse(isset($updated_xml->recurring)); // Old tag is gone
        $this->assertTrue(isset($updated_xml->reschedule));  // New tag is present
        $this->assertEquals('7 days', (string)$updated_xml->reschedule->interval);
        $this->assertEquals('completion_date', (string)$updated_xml->reschedule->from);
        $this->assertEquals('2025-07-15', (string)$updated_xml->due);
    }
}
