<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tasks;

use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use RuntimeException;

/**
 * Task for writing log records to disk.
 *
 * Handles file path generation, directory creation, and atomic file writes
 * with exclusive locking to prevent corruption in concurrent environments.
 *
 * @author Andy Defer
 */
class WriteLogTask
{
    public function __construct(
        private readonly LogPathService $pathService,
        private readonly LogSerializerService $serializer,
    ) {}

    /**
     * Execute the write operation for a log record.
     *
     * @param LogRecord $record The record to write
     * @throws RuntimeException If directory or file cannot be created/opened
     */
    public function execute(LogRecord $record): void
    {
        $filePath = $this->getFilePath($record->time);
        $this->ensureDirectoryExists($filePath);
        $this->writeToFile($filePath, $record);
    }

    /**
     * Get the file path for an ISO Zulu timestamp.
     *
     * @param IsoZuluTime $timestamp The timestamp to convert to file path
     * @return string The absolute file path
     */
    public function getFilePath(IsoZuluTime $timestamp): string
    {
        return $this->pathService->getHourlyFilePath($timestamp);
    }

    /**
     * Serialize a log record to JSON.
     *
     * @param LogRecord $record The record to serialize
     * @return string JSON representation with newline
     */
    public function serialize(LogRecord $record): string
    {
        return $this->serializer->serialize($record);
    }

    /**
     * Ensure the directory for a file path exists.
     *
     * @param string $filePath The full file path
     * @throws RuntimeException If directory cannot be created
     */
    private function ensureDirectoryExists(string $filePath): void
    {
        $directory = dirname($filePath);

        if (!$this->isDirectoryWritable($directory)) {
            $this->createDirectory($directory);
        }
    }

    /**
     * Check if a directory exists and is writable.
     */
    private function isDirectoryWritable(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * Create a directory with recursive permissions.
     *
     * @throws RuntimeException If directory cannot be created
     */
    private function createDirectory(string $directory): void
    {
        if (!@mkdir($directory, 0755, true)) {
            throw new RuntimeException("Cannot create log directory: {$directory}");
        }
    }

    /**
     * Write a serialized log record to a file with exclusive lock.
     *
     * @param string $filePath The destination file path
     * @param LogRecord $record The record to write
     * @throws RuntimeException If file cannot be opened
     */
    private function writeToFile(string $filePath, LogRecord $record): void
    {
        $handle = $this->openFile($filePath);
        $jsonLine = $this->serialize($record);

        $this->writeWithLock($handle, $jsonLine);
        $this->closeFile($handle);
    }

    /**
     * Open a file for appending.
     *
     * @param string $filePath The file to open
     * @return resource File handle
     * @throws RuntimeException If file cannot be opened
     */
    private function openFile(string $filePath)
    {
        $handle = @fopen($filePath, 'a');

        if ($handle === false) {
            throw new RuntimeException("Cannot open log file: {$filePath}");
        }

        return $handle;
    }

    /**
     * Write content to a file with exclusive lock.
     *
     * @param resource $handle File handle
     * @param string $content Content to write
     */
    private function writeWithLock($handle, string $content): void
    {
        if (flock($handle, LOCK_EX)) {
            fwrite($handle, $content);
            fflush($handle);
            flock($handle, LOCK_UN);
        }
    }

    /**
     * Close a file handle.
     *
     * @param resource $handle File handle
     */
    private function closeFile($handle): void
    {
        fclose($handle);
    }
}
