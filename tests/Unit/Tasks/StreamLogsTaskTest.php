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
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

/**
 * Test suite for StreamLogsTask.
 *
 * Validates streaming of all log records from a specific date,
 * including handling of missing files, invalid JSON, and corrupted lines.
 *
 * @author Andy Defer
 */
final class StreamLogsTaskTest extends UnitTestCase
{
    private StreamLogsTask $streamTask;
    private WriteLogTask $writeTask;
    private string $testLogPath;
    private string $currentDate;
    private LogSerializerService $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create isolated test environment
        $this->currentDate = date('Y-m-d');
        $this->testLogPath = sys_get_temp_dir() . '/test_logs_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService;

        $this->writeTask = new WriteLogTask($pathService, $this->serializer);
        $this->streamTask = new StreamLogsTask($pathService, $this->serializer);
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

    // ==================== BASIC FUNCTIONALITY TESTS ====================

    public function test_execute_returns_all_logs_for_specific_date(): void
    {
        // Arrange
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['value' => 1],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['value' => 2],
        ));

        $futureDate = date('Y-m-d', strtotime('+1 day'));
        $this->writeTask->execute($this->createLogRecord(
            time: $futureDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['value' => 3],
        ));

        // Act
        $results = $this->streamTask->execute($this->currentDate);

        // Assert
        $this->assertSame(2, $results->count());
    }

    public function test_execute_returns_all_logs_for_current_date_when_date_null(): void
    {
        // Arrange
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['value' => 1],
        ));

        // Act
        $results = $this->streamTask->execute();

        // Assert
        $this->assertSame(1, $results->count());
    }

    public function test_execute_returns_empty_collection_when_no_logs_for_date(): void
    {
        // Arrange
        $futureDate = date('Y-m-d', strtotime('+1 year'));

        // Act
        $results = $this->streamTask->execute($futureDate);

        // Assert
        $this->assertSame(0, $results->count());
        $this->assertTrue($results->isEmpty());
    }

    // ==================== MULTIPLE FILES TESTS ====================

    public function test_execute_handles_multiple_hour_files_for_same_date(): void
    {
        // Arrange
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['hour' => 10],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['hour' => 11],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T23:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['hour' => 23],
        ));

        // Act
        $results = $this->streamTask->execute($this->currentDate);

        // Assert
        $this->assertSame(3, $results->count());
    }

    // ==================== ERROR HANDLING TESTS ====================

    public function test_execute_handles_missing_directory_gracefully(): void
    {
        // Arrange
        $config = new LoggerConfig('/nonexistent/path/' . uniqid(), 30);
        $pathService = new LogPathService($config);
        $task = new StreamLogsTask($pathService, $this->serializer);

        // Act
        $results = $task->execute('2026-04-05');

        // Assert
        $this->assertSame(0, $results->count());
    }

    public function test_execute_handles_invalid_json_line_gracefully(): void
    {
        // Arrange
        $testDate = $this->currentDate;
        $testDir = $this->testLogPath . '/' . $testDate;
        mkdir($testDir, 0755, true);

        $testFile = $testDir . '/10-11.jsonl';
        file_put_contents($testFile, "invalid json line\n");

        // Act
        $results = $this->streamTask->execute($testDate);

        // Assert
        $this->assertSame(0, $results->count());
    }

    public function test_execute_handles_missing_fields_in_json(): void
    {
        // Arrange
        $testDate = $this->currentDate;
        $testDir = $this->testLogPath . '/' . $testDate;
        mkdir($testDir, 0755, true);

        $testFile = $testDir . '/10-11.jsonl';
        file_put_contents($testFile, json_encode(['time' => '2026-04-05T10:26:00Z']) . "\n");

        // Act
        $results = $this->streamTask->execute($testDate);

        // Assert
        $this->assertSame(0, $results->count());
    }

    public function test_execute_handles_mixed_valid_and_invalid_lines(): void
    {
        // Arrange
        $testDate = $this->currentDate;
        $testDir = $this->testLogPath . '/' . $testDate;
        mkdir($testDir, 0755, true);

        $testFile = $testDir . '/10-11.jsonl';

        file_put_contents($testFile, "invalid json line\n");

        $validRecord = $this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'valid_log',
            payloadData: ['value' => 42],
        );
        file_put_contents($testFile, $this->serializer->serialize($validRecord), FILE_APPEND);

        file_put_contents($testFile, "another invalid line\n", FILE_APPEND);

        // Act
        $results = $this->streamTask->execute($testDate);

        // Assert
        $this->assertSame(1, $results->count());
        $this->assertSame('valid_log', $results->first()->data->type);
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

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
