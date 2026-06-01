<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Tasks;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\ValueObjects\LoggerConfig;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use ErrorException;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use RuntimeException;

/**
 * Test suite for WriteLogTask.
 *
 * Validates log file writing, directory creation, file appending,
 * hour-based file bucketing, and error handling.
 *
 * @author Andy Defer
 */
final class WriteLogTaskTest extends UnitTestCase
{
    private WriteLogTask $task;
    private string $testLogPath;
    private LogSerializerService $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create isolated test environment
        $this->testLogPath = sys_get_temp_dir() . '/test_logs_' . uniqid();

        if (!is_dir($this->testLogPath)) {
            mkdir($this->testLogPath, 0777, true);
        }

        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService;
        $this->task = new WriteLogTask($pathService, $this->serializer);
    }

    protected function tearDown(): void
    {
        // Clean up temporary test files
        if (is_dir($this->testLogPath)) {
            $this->deleteDirectory($this->testLogPath);
        }
        parent::tearDown();
    }

    /**
     * Create a test log record with the given parameters.
     */
    private function createLogRecord(string $time, LogLevel $level, string $type, array $payloadData): LogRecord
    {
        $payload = new StrictDataObject($payloadData);
        $logData = new LogDataRecord(type: $type, payload: $payload);
        $isoTime = new IsoZuluTime($time);

        return new LogRecord(
            time: $isoTime,
            level: $level,
            data: $logData,
        );
    }

    // ==================== BASIC WRITE TESTS ====================

    public function test_execute_creates_directory_and_writes_log_entry(): void
    {
        // Arrange
        $currentDate = date('Y-m-d');
        $record = $this->createLogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: [
                'user_id' => 1,
                'ip' => '127.0.0.1',
            ],
        );

        // Act
        $this->task->execute($record);

        // Assert
        $filePath = $this->testLogPath . '/' . $currentDate . '/10-11.jsonl';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('"level":"info"', $content);
        $this->assertStringContainsString('"type":"user_login"', $content);
        $this->assertStringContainsString('"user_id":1', $content);
        $this->assertStringContainsString('"ip":"127.0.0.1"', $content);
    }

    // ==================== APPEND TESTS ====================

    public function test_execute_appends_multiple_entries_to_same_hour_file(): void
    {
        // Arrange
        $currentDate = date('Y-m-d');
        $record1 = $this->createLogRecord(
            time: $currentDate . 'T10:15:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['value' => 1],
        );

        $record2 = $this->createLogRecord(
            time: $currentDate . 'T10:45:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['value' => 2],
        );

        // Act
        $this->task->execute($record1);
        $this->task->execute($record2);

        // Assert
        $filePath = $this->testLogPath . '/' . $currentDate . '/10-11.jsonl';
        $content = file_get_contents($filePath);
        $lines = explode("\n", trim($content));

        $this->assertCount(2, $lines);
    }

    // ==================== HOUR BUCKETING TESTS ====================

    public function test_execute_creates_different_files_for_different_hours(): void
    {
        // Arrange
        $currentDate = date('Y-m-d');
        $record1 = $this->createLogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['value' => 1],
        );

        $record2 = $this->createLogRecord(
            time: $currentDate . 'T11:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['value' => 2],
        );

        // Act
        $this->task->execute($record1);
        $this->task->execute($record2);

        // Assert
        $this->assertFileExists($this->testLogPath . '/' . $currentDate . '/10-11.jsonl');
        $this->assertFileExists($this->testLogPath . '/' . $currentDate . '/11-12.jsonl');
    }

    // ==================== COMPLEX DATA TESTS ====================

    public function test_execute_handles_complex_data_structure(): void
    {
        // Arrange
        $currentDate = date('Y-m-d');

        $payload = new StrictDataObject([
            'event' => 'order_created',
            'order_id' => 12345,
            'amount' => 79.98,
            'paid' => true,
        ]);

        $logData = new LogDataRecord(type: 'order_created', payload: $payload);
        $isoTime = new IsoZuluTime($currentDate . 'T10:26:00Z');
        $record = new LogRecord(time: $isoTime, level: LogLevel::INFO, data: $logData);

        // Act
        $this->task->execute($record);

        // Assert
        $filePath = $this->testLogPath . '/' . $currentDate . '/10-11.jsonl';
        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);

        $this->assertSame('order_created', $decoded['data']['type']);
        $this->assertSame(12345, $decoded['data']['payload']['order_id']);
        $this->assertSame(79.98, $decoded['data']['payload']['amount']);
        $this->assertTrue($decoded['data']['payload']['paid']);
        $this->assertSame('order_created', $decoded['data']['payload']['event']);
    }

    // ==================== SERIALIZATION TESTS ====================

    public function test_execute_serializes_log_correctly(): void
    {
        // Arrange
        $currentDate = date('Y-m-d');
        $record = $this->createLogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test_event',
            payloadData: [
                'number' => 42,
                'text' => 'string_value',
                'flag' => true,
            ],
        );

        // Act
        $this->task->execute($record);

        // Assert
        $filePath = $this->testLogPath . '/' . $currentDate . '/10-11.jsonl';
        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);

        $this->assertSame('test_event', $decoded['data']['type']);
        $this->assertSame(42, $decoded['data']['payload']['number']);
        $this->assertSame('string_value', $decoded['data']['payload']['text']);
        $this->assertTrue($decoded['data']['payload']['flag']);
    }

    // ==================== ERROR HANDLING TESTS ====================

    #[WithoutErrorHandler]
    public function test_execute_throws_exception_when_write_fails(): void
    {
        // Arrange
        $invalidPath = sys_get_temp_dir() . '/invalid_path_' . uniqid();
        touch($invalidPath);

        $config = new LoggerConfig($invalidPath, 30);
        $pathService = new LogPathService($config);
        $task = new WriteLogTask($pathService, $this->serializer);

        $currentDate = date('Y-m-d');
        $record = $this->createLogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['data' => 'value'],
        );

        // Act & Assert
        $thrown = false;
        try {
            $task->execute($record);
        } catch (RuntimeException | ErrorException $e) {
            $thrown = true;
        } finally {
            if (file_exists($invalidPath)) {
                unlink($invalidPath);
            }
        }

        $this->assertTrue($thrown, 'Expected RuntimeException or ErrorException was not thrown');
    }

    #[WithoutErrorHandler]
    public function test_execute_throws_exception_when_file_not_writable(): void
    {
        // Arrange
        $readOnlyPath = sys_get_temp_dir() . '/readonly_' . uniqid();
        mkdir($readOnlyPath, 0555);

        $config = new LoggerConfig($readOnlyPath, 30);
        $pathService = new LogPathService($config);
        $task = new WriteLogTask($pathService, $this->serializer);

        $currentDate = date('Y-m-d');
        $record = $this->createLogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['data' => 'value'],
        );

        // Act & Assert
        $thrown = false;
        try {
            $task->execute($record);
        } catch (RuntimeException | ErrorException $e) {
            $thrown = true;
        } finally {
            chmod($readOnlyPath, 0755);
            rmdir($readOnlyPath);
        }

        $this->assertTrue($thrown, 'Expected RuntimeException or ErrorException was not thrown');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Recursively delete a directory and all its contents.
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        chmod($dir, 0777);

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                chmod($path, 0777);
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
