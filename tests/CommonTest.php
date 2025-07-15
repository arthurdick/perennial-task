<?php

use PHPUnit\Framework\TestCase;

class CommonTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean the tasks directory before each test
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }
    }

    public function testIsTaskReportable()
    {
        $now = new DateTimeImmutable('today');

        // Normal task, not completed -> reportable
        $xml_normal_active = new SimpleXMLElement('<task><name>Active</name></task>');
        $this->assertTrue(is_task_reportable($xml_normal_active, $now));

        // Normal task, completed -> not reportable
        $xml_normal_done = new SimpleXMLElement('<task><name>Done</name><history><entry>2025-01-01</entry></history></task>');
        $this->assertFalse(is_task_reportable($xml_normal_done, $now));

        // Due task, overdue -> reportable
        $xml_due_overdue = new SimpleXMLElement('<task><name>Overdue</name><due>' . $now->modify('-1 day')->format('Y-m-d') . '</due></task>');
        $this->assertTrue(is_task_reportable($xml_due_overdue, $now));

        // Due task, due today -> reportable
        $xml_due_today = new SimpleXMLElement('<task><name>Today</name><due>' . $now->format('Y-m-d') . '</due></task>');
        $this->assertTrue(is_task_reportable($xml_due_today, $now));

        // Due task, upcoming within preview -> reportable
        $xml_due_preview = new SimpleXMLElement('<task><name>Preview</name><due>' . $now->modify('+3 days')->format('Y-m-d') . '</due><preview>5</preview></task>');
        $this->assertTrue(is_task_reportable($xml_due_preview, $now));

        // Due task, upcoming outside preview -> not reportable
        $xml_due_future = new SimpleXMLElement('<task><name>Future</name><due>' . $now->modify('+10 days')->format('Y-m-d') . '</due><preview>5</preview></task>');
        $this->assertFalse(is_task_reportable($xml_due_future, $now));
    }

    public function testValidateDate()
    {
        $this->assertTrue(validate_date('2025-07-09'));
        $this->assertFalse(validate_date('2025-13-01'));
        $this->assertFalse(validate_date('not-a-date'));
        $this->assertFalse(validate_date('2025-02-30'));
    }

    public function testPluralizeDays()
    {
        $this->assertEquals('day', pluralize_days(1));
        $this->assertEquals('day', pluralize_days(-1));
        $this->assertEquals('days', pluralize_days(2));
        $this->assertEquals('days', pluralize_days(0));
        $this->assertEquals('days', pluralize_days(-5));
    }

    public function testGetTaskType()
    {
        // Normal Task
        $xml_normal = new SimpleXMLElement('<task><name>Normal Task</name></task>');
        $this->assertEquals('normal', get_task_type($xml_normal));

        // Due Task
        $xml_due = new SimpleXMLElement('<task><name>Due Task</name><due>2025-12-31</due></task>');
        $this->assertEquals('due', get_task_type($xml_due));

        // Recurring Task
        $xml_recurring = new SimpleXMLElement('<task><name>Recurring Task</name><recurring><completed>2025-07-01</completed><duration>7</duration></recurring></task>');
        $this->assertEquals('recurring', get_task_type($xml_recurring));
    }

    public function testValidateTaskFile()
    {
        // Create a valid task file
        $valid_xml_content = '<?xml version="1.0" encoding="UTF-8"?><task><name>Valid Task</name></task>';
        $valid_filepath = TASKS_DIR . '/valid_task.xml';
        file_put_contents($valid_filepath, $valid_xml_content);

        $this->assertTrue(validate_task_file($valid_filepath, true));

        // Create an invalid (malformed) task file
        $invalid_xml_content = '<task><name>Invalid Task</nme></task>';
        $invalid_filepath = TASKS_DIR . '/invalid_task.xml';
        file_put_contents($invalid_filepath, $invalid_xml_content);

        $this->assertFalse(validate_task_file($invalid_filepath, true));

        // Create a file that doesn't conform to the schema
        $non_conforming_xml = '<?xml version="1.0" encoding="UTF-8"?><badtask></badtask>';
        $non_conforming_filepath = TASKS_DIR . '/non_conforming.xml';
        file_put_contents($non_conforming_filepath, $non_conforming_xml);

        $this->assertFalse(validate_task_file($non_conforming_filepath, true));

        // Test with a non-existent file
        $this->assertFalse(validate_task_file(TASKS_DIR . '/non_existent.xml', true));
    }
    
    public function testSaveXmlFile()
    {
        $xml = new SimpleXMLElement('<task><name>Test Save</name></task>');
        $filepath = TASKS_DIR . '/test_save.xml';

        $this->assertTrue(save_xml_file($filepath, $xml));
        $this->assertFileExists($filepath);

        // Verify the content and schema location attribute
        $saved_content = file_get_contents($filepath);
        $this->assertStringContainsString('xsi:noNamespaceSchemaLocation="' . XSD_PATH . '"', $saved_content);
        $this->assertStringContainsString('<name>Test Save</name>', $saved_content);
        
        // Test validation of the saved file
        $this->assertTrue(validate_task_file($filepath));
    }

    public function testSanitizeFilename()
    {
        $this->assertEquals('a_simple_task', sanitize_filename('A Simple Task'));
        $this->assertEquals('task_with_numbers_123', sanitize_filename('Task with numbers 123'));
        $this->assertEquals('special_chars', sanitize_filename('Special-Chars!@#$%^&*()'));
        $this->assertEquals('extra_spaces', sanitize_filename('Extra   ---   Spaces'));
        $this->assertEquals('a', sanitize_filename('a'));
    }
}

