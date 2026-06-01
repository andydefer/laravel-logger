<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Tasks\WriteLogTask;
use Closure;

/**
 * Buffers log records before writing them to disk.
 *
 * Accumulates log records in memory and writes them in batches to improve
 * performance when logging multiple entries. The buffer automatically flushes
 * when it reaches its capacity or when the destructor is called.
 *
 * Records are grouped by file path before writing to minimize file operations.
 *
 * @author Andy Defer
 */
final class LogBufferService
{
    /**
     * @var array<LogRecord> Buffer storage for pending log records
     */
    private array $buffer = [];

    private int $bufferSize;

    private ?Closure $onFlush = null;

    /**
     * Create a new log buffer service.
     *
     * @param WriteLogTask $writeTask Task used to write records to disk
     * @param int $bufferSize Number of records to accumulate before auto-flush
     */
    public function __construct(
        private readonly WriteLogTask $writeTask,
        int $bufferSize = 100,
    ) {
        $this->bufferSize = $bufferSize;
    }

    /**
     * Add a log record to the buffer.
     *
     * Automatically triggers a flush if the buffer reaches capacity.
     *
     * @param LogRecord $record The record to buffer
     */
    public function push(LogRecord $record): void
    {
        $this->buffer[] = $record;

        if ($this->isBufferFull()) {
            $this->flush();
        }
    }

    /**
     * Write all buffered records to disk.
     *
     * Groups records by file path to optimize write operations, then writes
     * each batch. Resets the buffer after successful flush.
     */
    public function flush(): void
    {
        if ($this->isBufferEmpty()) {
            return;
        }

        $this->writeGroupedRecords();
        $this->notifyFlushListeners();
        $this->clearBuffer();
    }

    /**
     * Register a callback to execute after each flush.
     *
     * @param Closure(int): void $callback Receives the number of records flushed
     * @return self Returns the instance for method chaining
     */
    public function onFlush(Closure $callback): self
    {
        $this->onFlush = $callback;

        return $this;
    }

    /**
     * Get the current number of records in the buffer.
     */
    public function size(): int
    {
        return count($this->buffer);
    }

    /**
     * Check if the buffer contains any records.
     */
    public function isDirty(): bool
    {
        return !$this->isBufferEmpty();
    }

    /**
     * Get the maximum buffer capacity.
     */
    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    /**
     * Change the buffer capacity.
     *
     * If the new capacity is smaller than the current buffer size, a flush is
     * triggered immediately.
     *
     * @param int $size New buffer capacity
     * @return self Returns the instance for method chaining
     */
    public function setBufferSize(int $size): self
    {
        $this->bufferSize = $size;

        if ($this->isBufferFull()) {
            $this->flush();
        }

        return $this;
    }

    /**
     * Ensure all buffered records are written when the object is destroyed.
     */
    public function __destruct()
    {
        $this->flush();
    }

    /**
     * Check if the buffer has reached its capacity.
     */
    private function isBufferFull(): bool
    {
        return count($this->buffer) >= $this->bufferSize;
    }

    /**
     * Check if the buffer is empty.
     */
    private function isBufferEmpty(): bool
    {
        return empty($this->buffer);
    }

    /**
     * Group buffered records by file path and write each group.
     */
    private function writeGroupedRecords(): void
    {
        $grouped = $this->groupRecordsByFilePath();

        foreach ($grouped as $filePath => $records) {
            $this->writeBatch($filePath, $records);
        }
    }

    /**
     * Group buffered records by their destination file path.
     *
     * @return array<string, array<LogRecord>> Grouped records indexed by file path
     */
    private function groupRecordsByFilePath(): array
    {
        $grouped = [];

        foreach ($this->buffer as $record) {
            $filePath = $this->writeTask->getFilePath($record->time);
            $grouped[$filePath][] = $record;
        }

        return $grouped;
    }

    /**
     * Write a batch of records to a single file.
     *
     * @param string $filePath Destination file path
     * @param array<LogRecord> $records Records to write
     */
    private function writeBatch(string $filePath, array $records): void
    {
        $directory = dirname($filePath);

        if (!$this->isDirectoryPathValid($directory)) {
            return;
        }

        if (!$this->ensureDirectoryExists($directory)) {
            return;
        }

        $handle = $this->openFileForAppending($filePath);

        if ($handle === false) {
            return;
        }

        $this->writeRecordsWithLock($handle, $records);
        $this->closeFile($handle);
    }

    /**
     * Check if a directory path is valid for writing.
     */
    private function isDirectoryPathValid(string $directory): bool
    {
        return !empty($directory) && $directory !== '/' && $directory !== '.';
    }

    /**
     * Create directory if it doesn't exist.
     *
     * @return bool True if directory exists or was created successfully
     */
    private function ensureDirectoryExists(string $directory): bool
    {
        if (is_dir($directory)) {
            return true;
        }

        return @mkdir($directory, 0755, true);
    }

    /**
     * Open a file for appending with error suppression.
     *
     * @return resource|false File handle or false on failure
     */
    private function openFileForAppending(string $filePath)
    {
        return @fopen($filePath, 'a');
    }

    /**
     * Write records to file with exclusive lock.
     *
     * @param resource $handle File handle
     * @param array<LogRecord> $records Records to write
     */
    private function writeRecordsWithLock($handle, array $records): void
    {
        if (!flock($handle, LOCK_EX)) {
            return;
        }

        foreach ($records as $record) {
            $jsonLine = $this->writeTask->serialize($record);
            @fwrite($handle, $jsonLine);
        }

        @fflush($handle);
        flock($handle, LOCK_UN);
    }

    /**
     * Close the file handle with error suppression.
     *
     * @param resource $handle File handle
     */
    private function closeFile($handle): void
    {
        @fclose($handle);
    }

    /**
     * Execute the on-flush callback if registered.
     */
    private function notifyFlushListeners(): void
    {
        if ($this->onFlush !== null) {
            ($this->onFlush)(count($this->buffer));
        }
    }

    /**
     * Clear the buffer after successful flush.
     */
    private function clearBuffer(): void
    {
        $this->buffer = [];
    }
}
