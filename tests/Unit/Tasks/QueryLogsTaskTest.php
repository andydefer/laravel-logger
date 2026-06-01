<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Tasks;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\ValueObjects\LoggerConfig;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

/**
 * Test suite for QueryLogsTask.
 *
 * Validates filtering of log records by type, level, and date range.
 *
 * @author Andy Defer
 */
final class QueryLogsTaskTest extends UnitTestCase
{
    private QueryLogsTask $queryTask;
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
        $this->queryTask = new QueryLogsTask($pathService, $this->serializer);
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

    /**
     * Get the full day date range for the current date.
     */
    private function getFullDayRange(): array
    {
        return [
            'from' => new IsoZuluTime($this->currentDate . 'T00:00:00Z'),
            'to' => new IsoZuluTime($this->currentDate . 'T23:59:59Z'),
        ];
    }

    // ==================== NO FILTER TESTS ====================

    public function test_execute_returns_all_logs_when_no_filters(): void
    {
        // Arrange
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 1],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::ERROR,
            type: 'payment_failed',
            payloadData: ['payment_id' => 123],
        ));

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: null,
            level: null,
        );

        // Act
        $results = $this->queryTask->execute($query);

        // Assert
        $this->assertSame(2, $results->count());
    }

    // ==================== FILTER BY TYPE TESTS ====================

    public function test_execute_filters_by_type(): void
    {
        // Arrange
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 1],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 2],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T12:26:00Z',
            level: LogLevel::ERROR,
            type: 'payment_failed',
            payloadData: ['payment_id' => 123],
        ));

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: 'user_login',
            level: null,
        );

        // Act
        $results = $this->queryTask->execute($query);

        // Assert
        $this->assertSame(2, $results->count());

        foreach ($results as $result) {
            $this->assertSame('user_login', $result->data->type);
        }
    }

    // ==================== FILTER BY LEVEL TESTS ====================

    public function test_execute_filters_by_level(): void
    {
        // Arrange
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: [],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::ERROR,
            type: 'payment_failed',
            payloadData: ['payment_id' => 123],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T12:26:00Z',
            level: LogLevel::WARNING,
            type: 'system_warning',
            payloadData: ['reason' => 'high_memory'],
        ));

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: null,
            level: LogLevel::ERROR,
        );

        // Act
        $results = $this->queryTask->execute($query);

        // Assert
        $this->assertSame(1, $results->count());
        $this->assertSame('payment_failed', $results->first()->data->type);
    }

    // ==================== FILTER BY DATE RANGE TESTS ====================

    public function test_execute_filters_by_date_range(): void
    {
        // Arrange
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T09:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [],
        ));

        $query = new LogQueryRecord(
            from: new IsoZuluTime($this->currentDate . 'T10:00:00Z'),
            to: new IsoZuluTime($this->currentDate . 'T10:59:59Z'),
            type: null,
            level: null,
        );

        // Act
        $results = $this->queryTask->execute($query);

        // Assert
        $this->assertSame(1, $results->count());
        $this->assertStringContainsString('10:26', $results->first()->time->getValue());
    }

    // ==================== COMBINED FILTERS TESTS ====================

    public function test_execute_combines_multiple_filters(): void
    {
        // Arrange
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 1],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 2],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::ERROR,
            type: 'payment_failed',
            payloadData: ['payment_id' => 123],
        ));

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: 'user_login',
            level: LogLevel::INFO,
        );

        // Act
        $results = $this->queryTask->execute($query);

        // Assert
        $this->assertSame(2, $results->count());
    }

    // ==================== NO MATCH TESTS ====================

    public function test_execute_returns_empty_collection_when_no_matches(): void
    {
        // Arrange
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 1],
        ));

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: 'nonexistent_type',
            level: null,
        );

        // Act
        $results = $this->queryTask->execute($query);

        // Assert
        $this->assertSame(0, $results->count());
        $this->assertTrue($results->isEmpty());
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_execute_handles_logs_without_type_field(): void
    {
        // Arrange - Create a log with unknown type
        $payload = new StrictDataObject(['message' => 'Simple log without type']);
        $logData = new LogDataRecord(type: 'unknown', payload: $payload);
        $isoTime = new IsoZuluTime($this->currentDate . 'T10:26:00Z');

        $record = new LogRecord(
            time: $isoTime,
            level: LogLevel::INFO,
            data: $logData,
        );

        $this->writeTask->execute($record);

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: 'user_login',
            level: null,
        );

        // Act
        $results = $this->queryTask->execute($query);

        // Assert
        $this->assertSame(0, $results->count());
    }

    public function test_execute_handles_corrupted_json_lines_gracefully(): void
    {
        // Arrange
        $testDate = $this->currentDate;
        $testDir = $this->testLogPath . '/' . $testDate;
        mkdir($testDir, 0755, true);

        $testFile = $testDir . '/10-11.jsonl';
        file_put_contents($testFile, "corrupted json line\n");
        file_put_contents($testFile, $this->serializer->serialize($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'valid_log',
            payloadData: ['value' => 42],
        )), FILE_APPEND);

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: null,
            level: null,
        );

        // Act
        $results = $this->queryTask->execute($query);

        // Assert
        $this->assertSame(1, $results->count());
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
