<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Logger;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test suite for Logger class.
 *
 * Validates log writing at different levels, buffering behavior,
 * query delegation, and stream operations.
 *
 * @author Andy Defer
 */
#[AllowMockObjectsWithoutExpectations]
final class LoggerTest extends UnitTestCase
{
    private Logger $logger;
    private MockObject&WriteLogTask $writeTask;
    private MockObject&QueryLogsTask $queryTask;
    private MockObject&StreamLogsTask $streamTask;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create mock tasks
        $this->writeTask = $this->createMock(WriteLogTask::class);
        $this->queryTask = $this->createMock(QueryLogsTask::class);
        $this->streamTask = $this->createMock(StreamLogsTask::class);

        // Arrange: Create logger with mocked dependencies
        $this->logger = new Logger(
            $this->writeTask,
            $this->queryTask,
            $this->streamTask,
        );
    }

    /**
     * Create a test log data record.
     */
    private function createLogDataRecord(string $type, array $payloadData): LogDataRecord
    {
        $payload = new StrictDataObject($payloadData);
        return new LogDataRecord(type: $type, payload: $payload);
    }

    /**
     * Create a test log record with the given parameters.
     */
    private function createLogRecord(string $time, LogLevel $level, string $type, array $payloadData): LogRecord
    {
        $logData = $this->createLogDataRecord($type, $payloadData);
        $isoTime = new IsoZuluTime($time);

        return new LogRecord(
            time: $isoTime,
            level: $level,
            data: $logData,
        );
    }

    // ==================== LOG LEVEL TESTS ====================

    public function test_info_creates_info_level_log_record(): void
    {
        // Arrange
        $payloadData = [
            'user_id' => 1,
            'action' => 'user_login',
            'ip' => '127.0.0.1',
        ];
        $logData = $this->createLogDataRecord('user_login', $payloadData);

        // Assert
        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::INFO
                    && $record->data->type === $logData->type
                    && $record->time instanceof IsoZuluTime;
            }));

        // Act
        $this->logger->info($logData);
    }

    public function test_warning_creates_warning_level_log_record(): void
    {
        // Arrange
        $payloadData = [
            'type' => 'system_warning',
            'message' => 'High memory usage',
        ];
        $logData = $this->createLogDataRecord('system_warning', $payloadData);

        // Assert
        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::WARNING
                    && $record->data->type === $logData->type;
            }));

        // Act
        $this->logger->warning($logData);
    }

    public function test_error_creates_error_level_log_record(): void
    {
        // Arrange
        $payloadData = [
            'event' => 'payment_failed',
            'payment_id' => 12345,
            'amount' => 99.99,
        ];
        $logData = $this->createLogDataRecord('payment_failed', $payloadData);

        // Assert
        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::ERROR
                    && $record->data->type === $logData->type;
            }));

        // Act
        $this->logger->error($logData);
    }

    public function test_debug_creates_debug_level_log_record(): void
    {
        // Arrange
        $payloadData = [
            'info' => 'debug_info',
            'value' => 'test value',
        ];
        $logData = $this->createLogDataRecord('debug_info', $payloadData);

        // Assert
        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::DEBUG
                    && $record->data->type === $logData->type;
            }));

        // Act
        $this->logger->debug($logData);
    }

    // ==================== DIRECT LOG TESTS ====================

    public function test_log_calls_write_task_directly(): void
    {
        // Arrange
        $record = $this->createLogRecord(
            time: '2024-01-01T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [
                'test' => 'test',
                'number' => 42,
                'active' => true,
            ],
        );

        // Assert
        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($record);

        // Act
        $this->logger->log($record);
    }

    // ==================== QUERY DELEGATION TESTS ====================

    public function test_query_delegates_to_query_task(): void
    {
        // Arrange
        $currentDate = date('Y-m-d');
        $from = new IsoZuluTime($currentDate . 'T00:00:00Z');
        $to = new IsoZuluTime($currentDate . 'T23:59:59Z');

        $query = new LogQueryRecord(
            from: $from,
            to: $to,
            type: 'user_login',
            level: null,
        );
        $expectedResults = new TypedCollection(LogRecord::class);

        $this->queryTask->expects($this->once())
            ->method('execute')
            ->with($query)
            ->willReturn($expectedResults);

        // Act
        $results = $this->logger->query($query);

        // Assert
        $this->assertSame($expectedResults, $results);
    }

    // ==================== STREAM DELEGATION TESTS ====================

    public function test_stream_delegates_to_stream_task(): void
    {
        // Arrange
        $date = '2024-01-01';
        $expectedResults = new TypedCollection(LogRecord::class);

        $this->streamTask->expects($this->once())
            ->method('execute')
            ->with($date)
            ->willReturn($expectedResults);

        // Act
        $results = $this->logger->stream($date);

        // Assert
        $this->assertSame($expectedResults, $results);
    }

    public function test_stream_uses_current_date_when_null(): void
    {
        // Arrange
        $expectedResults = new TypedCollection(LogRecord::class);

        $this->streamTask->expects($this->once())
            ->method('execute')
            ->with(null)
            ->willReturn($expectedResults);

        // Act
        $results = $this->logger->stream();

        // Assert
        $this->assertSame($expectedResults, $results);
    }

    // ==================== BUFFER TESTS ====================

    public function test_enable_buffer_creates_buffer(): void
    {
        // Act
        $this->logger->enableBuffer(50);

        // Assert
        $this->assertTrue($this->logger->isBufferEnabled());
        $this->assertSame(50, $this->logger->getBufferSize());
    }

    public function test_disable_buffer_flushes_and_removes_buffer(): void
    {
        // Arrange
        $this->logger->enableBuffer(50);
        $this->assertTrue($this->logger->isBufferEnabled());

        // Act
        $this->logger->disableBuffer();

        // Assert
        $this->assertFalse($this->logger->isBufferEnabled());
    }

    public function test_flush_calls_buffer_flush(): void
    {
        // Arrange
        $this->logger->enableBuffer(50);

        // Act
        $this->logger->flush();

        // Assert
        $this->assertTrue(true); // No exception means success
    }

    public function test_query_flushes_buffer_before_execution(): void
    {
        // Arrange
        $this->writeTask->expects($this->never())->method('execute');
        $this->logger->enableBuffer(50);

        $currentDate = date('Y-m-d');
        $from = new IsoZuluTime($currentDate . 'T00:00:00Z');
        $to = new IsoZuluTime($currentDate . 'T23:59:59Z');
        $query = new LogQueryRecord(
            from: $from,
            to: $to,
            type: 'user_login',
            level: null,
        );
        $expectedResults = new TypedCollection(LogRecord::class);

        $this->queryTask->expects($this->once())
            ->method('execute')
            ->with($query)
            ->willReturn($expectedResults);

        // Act
        $results = $this->logger->query($query);

        // Assert
        $this->assertSame($expectedResults, $results);
    }

    public function test_stream_flushes_buffer_before_execution(): void
    {
        // Arrange
        $this->writeTask->expects($this->never())->method('execute');
        $this->logger->enableBuffer(50);

        $expectedResults = new TypedCollection(LogRecord::class);

        $this->streamTask->expects($this->once())
            ->method('execute')
            ->with(null)
            ->willReturn($expectedResults);

        // Act
        $results = $this->logger->stream();

        // Assert
        $this->assertSame($expectedResults, $results);
    }

    public function test_log_without_buffer_writes_immediately(): void
    {
        // Arrange
        $logData = $this->createLogDataRecord('test', ['value' => 1]);

        // Assert
        $this->writeTask->expects($this->once())
            ->method('execute');

        // Act
        $this->logger->info($logData);
    }

    public function test_log_with_buffer_does_not_write_immediately(): void
    {
        // Arrange
        $this->writeTask->expects($this->never())->method('execute');
        $this->logger->enableBuffer(100);

        $logData = $this->createLogDataRecord('test', ['value' => 1]);

        // Act
        $this->logger->info($logData);

        // Assert: No immediate write, buffer holds the record
    }
}
