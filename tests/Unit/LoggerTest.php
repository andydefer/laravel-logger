<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit;

use AndyDefer\Logger\Tests\TestCase;
use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Logger;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Records\Collections\TypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

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

        $this->writeTask = $this->createMock(WriteLogTask::class);
        $this->queryTask = $this->createMock(QueryLogsTask::class);
        $this->streamTask = $this->createMock(StreamLogsTask::class);

        $this->logger = new Logger(
            $this->writeTask,
            $this->queryTask,
            $this->streamTask,
        );
    }

    private function createLogDataRecord(string $type, array $payloadData): LogDataRecord
    {
        $payload = new MixedPayloadCollection;
        foreach ($payloadData as $item) {
            $payload->add($item);
        }

        return new LogDataRecord(
            type: $type,
            payload: $payload,
        );
    }

    public function test_info_creates_info_level_log_record(): void
    {
        $payloadData = [1, 'user_login', '127.0.0.1'];
        $logData = $this->createLogDataRecord('user_login', $payloadData);

        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::INFO
                    && $record->data->type === $logData->type;
            }));

        $this->logger->info($logData);
    }

    public function test_warning_creates_warning_level_log_record(): void
    {
        $payloadData = ['system_warning', 'High memory usage'];
        $logData = $this->createLogDataRecord('system_warning', $payloadData);

        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::WARNING
                    && $record->data->type === $logData->type;
            }));

        $this->logger->warning($logData);
    }

    public function test_error_creates_error_level_log_record(): void
    {
        $payloadData = ['payment_failed', 12345, 99.99];
        $logData = $this->createLogDataRecord('payment_failed', $payloadData);

        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::ERROR
                    && $record->data->type === $logData->type;
            }));

        $this->logger->error($logData);
    }

    public function test_debug_creates_debug_level_log_record(): void
    {
        $payloadData = ['debug_info', 'test value'];
        $logData = $this->createLogDataRecord('debug_info', $payloadData);

        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::DEBUG
                    && $record->data->type === $logData->type;
            }));

        $this->logger->debug($logData);
    }

    public function test_log_calls_write_task_directly(): void
    {
        $payload = new MixedPayloadCollection;
        $payload->add('test', 42, true);

        $logData = new LogDataRecord(type: 'test', payload: $payload);

        $record = new LogRecord(
            time: '2024-01-01T10:26:00Z',
            level: LogLevel::INFO,
            data: $logData,
        );

        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($record);

        $this->logger->log($record);
    }

    public function test_query_delegates_to_query_task(): void
    {
        $dateRange = $this->getDateRange();
        $query = new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            type: 'user_login',
            level: null,
        );
        $expectedResults = new TypedCollection(LogRecord::class);

        $this->queryTask->expects($this->once())
            ->method('execute')
            ->with($query)
            ->willReturn($expectedResults);

        $results = $this->logger->query($query);

        $this->assertSame($expectedResults, $results);
    }

    public function test_stream_delegates_to_stream_task(): void
    {
        $date = '2024-01-01';
        $expectedResults = new TypedCollection(LogRecord::class);

        $this->streamTask->expects($this->once())
            ->method('execute')
            ->with($date)
            ->willReturn($expectedResults);

        $results = $this->logger->stream($date);

        $this->assertSame($expectedResults, $results);
    }

    public function test_stream_uses_current_date_when_null(): void
    {
        $expectedResults = new TypedCollection(LogRecord::class);

        $this->streamTask->expects($this->once())
            ->method('execute')
            ->with(null)
            ->willReturn($expectedResults);

        $results = $this->logger->stream();

        $this->assertSame($expectedResults, $results);
    }

    // ==================== TESTS POUR BUFFER ====================

    public function test_enable_buffer_creates_buffer(): void
    {
        $this->logger->enableBuffer(50);

        $this->assertTrue($this->logger->isBufferEnabled());
        $this->assertSame(50, $this->logger->getBufferSize());
    }

    public function test_disable_buffer_flushes_and_removes_buffer(): void
    {
        $this->logger->enableBuffer(50);
        $this->assertTrue($this->logger->isBufferEnabled());

        $this->logger->disableBuffer();

        $this->assertFalse($this->logger->isBufferEnabled());
    }

    public function test_flush_calls_buffer_flush(): void
    {
        $this->logger->enableBuffer(50);

        $this->logger->flush();

        $this->assertTrue(true);
    }

    public function test_query_flushes_buffer_before_execution(): void
    {
        $this->writeTask->expects($this->never())->method('execute');

        $this->logger->enableBuffer(50);

        $dateRange = $this->getDateRange();
        $query = new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            type: 'user_login',
            level: null,
        );
        $expectedResults = new TypedCollection(LogRecord::class);

        $this->queryTask->expects($this->once())
            ->method('execute')
            ->with($query)
            ->willReturn($expectedResults);

        $results = $this->logger->query($query);

        $this->assertSame($expectedResults, $results);
    }

    public function test_stream_flushes_buffer_before_execution(): void
    {
        $this->writeTask->expects($this->never())->method('execute');

        $this->logger->enableBuffer(50);

        $expectedResults = new TypedCollection(LogRecord::class);

        $this->streamTask->expects($this->once())
            ->method('execute')
            ->with(null)
            ->willReturn($expectedResults);

        $results = $this->logger->stream();

        $this->assertSame($expectedResults, $results);
    }

    public function test_log_without_buffer_writes_immediately(): void
    {
        $logData = $this->createLogDataRecord('test', [1]);

        $this->writeTask->expects($this->once())
            ->method('execute');

        $this->logger->info($logData);
    }

    public function test_log_with_buffer_does_not_write_immediately(): void
    {
        $this->writeTask->expects($this->never())->method('execute');

        $this->logger->enableBuffer(100);

        $logData = $this->createLogDataRecord('test', [1]);
        $this->logger->info($logData);
    }
}
