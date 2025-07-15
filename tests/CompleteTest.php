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

    public function testCompleteDueTask_UpdateDate()
    {
        $xml = new SimpleXMLElement('<task><name>Due Task To Update</name><due>2025-07-01</due></task>');
        $filepath = TASKS_DIR . '/due_update.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runCompleteScript($filepath, ['2025-07-10', '2026-01-01']);

        $this->assertStringContainsString("Task 'Due Task To Update' was completed on 2025-07-10.", $output);
        $this->assertStringContainsString("new due date of 2026-01-01", $output);

        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('2026-01-01', (string)$updated_xml->due);
        $this->assertEquals('2025-07-10', (string)$updated_xml->history->entry);
    }

    public function testCompleteRecurringTask_Yes()
    {
        $xml = new SimpleXMLElement('<task><name>Recurring Task To Update</name><recurring><completed>2025-07-01</completed><duration>7</duration></recurring></task>');
        $filepath = TASKS_DIR . '/recurring_update.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runCompleteScript($filepath, ['2025-07-08', 'y']);

        $this->assertStringContainsString("has been updated with a new completion date of 2025-07-08", $output);
        $this->assertFileExists($filepath);

        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('2025-07-08', (string)$updated_xml->recurring->completed);
        $this->assertEquals('2025-07-08', (string)$updated_xml->history->entry);
    }
}
