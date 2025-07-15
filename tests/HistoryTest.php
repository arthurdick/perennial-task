<?php

use PHPUnit\Framework\TestCase;

class HistoryTest extends TestCase
{
    private $script_path = __DIR__ . '/../history.php';

    protected function setUp(): void
    {
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }
    }

    private function runHistoryScript(string $task_filepath): string
    {
        global $argv;
        $argv = ['history.php', $task_filepath];

        ob_start();
        include $this->script_path;
        return ob_get_clean();
    }

    public function testHistoryForTaskWithHistory()
    {
        $xml = new SimpleXMLElement('<task><name>A Task With History</name><history><entry>2025-01-01</entry><entry>2025-01-15</entry></history></task>');
        $filepath = TASKS_DIR . '/history.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runHistoryScript($filepath);

        $this->assertStringContainsString('History for task: A Task With History', $output);
        $this->assertStringContainsString('- 2025-01-01', $output);
        $this->assertStringContainsString('- 2025-01-15', $output);
    }
    
    public function testHistoryForTaskWithoutHistory()
    {
        $xml = new SimpleXMLElement('<task><name>A Task Without History</name></task>');
        $filepath = TASKS_DIR . '/no_history.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runHistoryScript($filepath);

        $this->assertStringContainsString('History for task: A Task Without History', $output);
        $this->assertStringContainsString('No completion history found for this task.', $output);
    }
}
