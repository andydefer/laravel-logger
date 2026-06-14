<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Contracts;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;

/**
 * Contract for structured logging operations.
 *
 * Provides a clean interface for writing structured log entries,
 * querying existing logs, and streaming log files.
 *
 * @author Andy Defer
 */
interface LoggerInterface
{
    /**
     * Write a complete log record directly.
     *
     * @param LogRecord $record The fully constructed log record to write
     */
    public function log(LogRecord $record): void;

    /**
     * Write an INFO level log entry.
     *
     * @param AbstractRecord $data The data payload to log (will be wrapped in a LogRecord)
     */
    public function info(AbstractRecord $data): void;

    /**
     * Write a WARNING level log entry.
     *
     * @param AbstractRecord $data The data payload to log (will be wrapped in a LogRecord)
     */
    public function warning(AbstractRecord $data): void;

    /**
     * Write an ERROR level log entry.
     *
     * @param AbstractRecord $data The data payload to log (will be wrapped in a LogRecord)
     */
    public function error(AbstractRecord $data): void;

    /**
     * Write a DEBUG level log entry.
     *
     * @param AbstractRecord $data The data payload to log (will be wrapped in a LogRecord)
     */
    public function debug(AbstractRecord $data): void;

    /**
     * Query logs based on search criteria.
     *
     * @param LogQueryRecord $query The query parameters (date range, type, level)
     * @return AbstractTypedCollection<LogRecord> Collection of matching log records
     */
    public function query(LogQueryRecord $query): AbstractTypedCollection;

    /**
     * Stream all logs for a specific date.
     *
     * @param string|null $date The date in YYYY-MM-DD format (uses current date if null)
     * @return AbstractTypedCollection<LogRecord> Collection of all logs from the specified date
     */
    public function stream(?string $date = null): AbstractTypedCollection;
}
