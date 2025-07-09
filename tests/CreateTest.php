<?php

use PHPUnit\Framework\TestCase;

class CreateTest extends TestCase
{
    private $script_path = __DIR__ . '/../create.php';

    protected function setUp(): void
    {
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }
    }

    private function runCreateScript(array $inputs): array
    {
        // The stream that will provide the fake user input.
        $input_stream = fopen('php://memory', 'r+');
        foreach ($inputs as $input) {
            fwrite($input_stream, $input . PHP_EOL);
        }
        rewind($input_stream);

        // Define the mock function that reads from our stream and set it globally.
        $GLOBALS['__MOCK_PROMPT_USER_FUNC'] = function(string $prompt) use ($input_stream): string {
            $line = fgets($input_stream);
            return $line !== false ? trim($line) : '';
        };

        ob_start();
        // The included script will now use our mock prompt_user function
        include $this->script_path;
        $output = ob_get_clean();
        
        // Clean up the global and the stream resource.
        unset($GLOBALS['__MOCK_PROMPT_USER_FUNC']);
        fclose($input_stream);

        return ['output' => $output, 'files' => glob(TASKS_DIR . '/*.xml')];
    }
    
    public function testCreateNormalTask()
    {
        $inputs = [
            'Test Normal Task', // name
            'normal',           // type
            '',                 // preview (skip)
        ];

        $result = $this->runCreateScript($inputs);
        
        $this->assertStringContainsString('Success! Task file created', $result['output']);
        $this->assertCount(1, $result['files']);
        
        $filepath = $result['files'][0];
        $this->assertFileExists($filepath);
        
        $xml = simplexml_load_file($filepath);
        $this->assertEquals('Test Normal Task', (string)$xml->name);
        $this->assertFalse(isset($xml->due));
        $this->assertFalse(isset($xml->recurring));
        $this->assertFalse(isset($xml->preview));
    }

    public function testCreateDueTask()
    {
        $inputs = [
            'Test Due Task',    // name
            'due',              // type
            '2025-12-25',       // due date
            '5',                // preview
        ];

        $result = $this->runCreateScript($inputs);

        $this->assertStringContainsString('Success! Task file created', $result['output']);
        $this->assertCount(1, $result['files']);

        $filepath = $result['files'][0];
        $xml = simplexml_load_file($filepath);

        $this->assertEquals('Test Due Task', (string)$xml->name);
        $this->assertEquals('2025-12-25', (string)$xml->due);
        $this->assertEquals('5', (string)$xml->preview);
        $this->assertFalse(isset($xml->recurring));
    }
    
    public function testCreateRecurringTask()
    {
        $inputs = [
            'Test Recurring Task', // name
            'recurring',           // type
            '2025-07-01',          // last completed
            '14',                  // duration
            '3',                   // preview
        ];

        $result = $this->runCreateScript($inputs);

        $this->assertStringContainsString('Success! Task file created', $result['output']);
        $this->assertCount(1, $result['files']);

        $filepath = $result['files'][0];
        $xml = simplexml_load_file($filepath);

        $this->assertEquals('Test Recurring Task', (string)$xml->name);
        $this->assertEquals('2025-07-01', (string)$xml->recurring->completed);
        $this->assertEquals('14', (string)$xml->recurring->duration);
        $this->assertEquals('3', (string)$xml->preview);
        $this->assertFalse(isset($xml->due));
    }
    
    public function testFilenameSanitizationAndUniqueness()
    {
        // First task
        $this->runCreateScript(['Test @Task!', 'normal', '']);
        $this->assertFileExists(TASKS_DIR . '/test_task.xml');

        // Second task with the same name
        $this->runCreateScript(['Test @Task!', 'normal', '']);
        $this->assertFileExists(TASKS_DIR . '/test_task_1.xml');
        
        // Third task
        $this->runCreateScript(['Test @Task!', 'normal', '']);
        $this->assertFileExists(TASKS_DIR . '/test_task_2.xml');
    }
}
