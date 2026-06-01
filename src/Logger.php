<?php

declare(strict_types=1);

namespace AndyDefer\Logger;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogBufferService;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

/**
 * Structured logger with optional buffering support.
 *
 * Provides methods for writing logs at different severity levels,
 * querying existing logs, and streaming log files. Buffer mode
 * accumulates logs in memory for better performance.
 *
 * @author Andy Defer
 */
final class Logger implements LoggerInterface
{
    private ?LogBufferService $buffer = null;
    private bool $isBufferEnabled = false;

    public function __construct(
        private readonly WriteLogTask $writeLogTask,
        private readonly QueryLogsTask $queryLogsTask,
        private readonly StreamLogsTask $streamLogsTask,
    ) {}

    /**
     * Enable buffering mode for better write performance.
     *
     * @param int $size Number of records to buffer before auto-flush
     * @return self Returns the instance for method chaining
     */
    public function enableBuffer(int $size = 100): self
    {
        $this->isBufferEnabled = true;
        $this->buffer = new LogBufferService($this->writeLogTask, $size);

        return $this;
    }

    /**
     * Disable buffering mode and flush any pending records.
     *
     * @return self Returns the instance for method chaining
     */
    public function disableBuffer(): self
    {
        if ($this->buffer !== null) {
            $this->buffer->flush();
        }

        $this->isBufferEnabled = false;
        $this->buffer = null;

        return $this;
    }

    /**
     * Flush any pending records to disk immediately.
     */
    public function flush(): void
    {
        if ($this->buffer !== null) {
            $this->buffer->flush();
        }
    }

    /**
     * Check if buffering mode is currently enabled.
     */
    public function isBufferEnabled(): bool
    {
        return $this->isBufferEnabled;
    }

    /**
     * Get the current buffer capacity (0 if buffering is disabled).
     */
    public function getBufferSize(): int
    {
        return $this->buffer?->getBufferSize() ?? 0;
    }

    /**
     * Write a complete log record.
     *
     * @param LogRecord $record The fully constructed log record
     */
    public function log(LogRecord $record): void
    {
        $this->write($record);
    }

    /**
     * Write an INFO level log entry.
     *
     * @param AbstractRecord $data The data payload to log
     */
    public function info(AbstractRecord $data): void
    {
        $this->write($this->createLogRecord(LogLevel::INFO, $data));
    }

    /**
     * Write a WARNING level log entry.
     *
     * @param AbstractRecord $data The data payload to log
     */
    public function warning(AbstractRecord $data): void
    {
        $this->write($this->createLogRecord(LogLevel::WARNING, $data));
    }

    /**
     * Write an ERROR level log entry.
     *
     * @param AbstractRecord $data The data payload to log
     */
    public function error(AbstractRecord $data): void
    {
        $this->write($this->createLogRecord(LogLevel::ERROR, $data));
    }

    /**
     * Write a DEBUG level log entry.
     *
     * @param AbstractRecord $data The data payload to log
     */
    public function debug(AbstractRecord $data): void
    {
        $this->write($this->createLogRecord(LogLevel::DEBUG, $data));
    }

    /**
     * Query logs based on search criteria.
     *
     * @param LogQueryRecord $query The query parameters
     * @return TypedCollection<LogRecord> Collection of matching log records
     */
    public function query(LogQueryRecord $query): TypedCollection
    {
        $this->flushBufferBeforeQuery();

        return $this->queryLogsTask->execute($query);
    }

    /**
     * Stream all logs for a specific date.
     *
     * @param string|null $date The date in YYYY-MM-DD format (uses current date if null)
     * @return TypedCollection<LogRecord> Collection of all logs from the specified date
     */
    public function stream(?string $date = null): TypedCollection
    {
        $this->flushBufferBeforeQuery();

        return $this->streamLogsTask->execute($date);
    }

    /**
     * Write a log record (buffered or direct).
     *
     * @param LogRecord $record The record to write
     */
    private function write(LogRecord $record): void
    {
        if ($this->isBufferEnabled && $this->buffer !== null) {
            $this->buffer->push($record);
        } else {
            $this->writeLogTask->execute($record);
        }
    }

    /**
     * Create a log record with the current timestamp.
     *
     * @param LogLevel $level The severity level
     * @param AbstractRecord $data The payload data
     * @return LogRecord The constructed record
     */
    private function createLogRecord(LogLevel $level, AbstractRecord $data): LogRecord
    {
        return new LogRecord(
            time: new IsoZuluTime($this->getCurrentTimestamp()),
            level: $level,
            data: $data,
        );
    }

    /**
     * Get the current timestamp in ISO 8601 Zulu format.
     *
     * @return string Current timestamp (e.g., "2024-01-01T12:00:00Z")
     */
    private function getCurrentTimestamp(): string
    {
        return date('Y-m-d\TH:i:s\Z');
    }

    /**
     * Flush buffer before executing queries for consistency.
     */
    private function flushBufferBeforeQuery(): void
    {
        if ($this->buffer !== null) {
            $this->buffer->flush();
        }
    }
}
