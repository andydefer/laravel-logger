<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tasks;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;

/**
 * Task for streaming all log records from a specific date.
 *
 * Reads all log files for a given date and returns them as a collection.
 * Useful for exporting logs or performing full-day analysis.
 *
 * @author Andy Defer
 */
class StreamLogsTask
{
    public function __construct(
        private readonly LogPathService $pathService,
        private readonly LogSerializerService $serializer,
    ) {}

    /**
     * Execute the stream operation for a specific date.
     *
     * @param string|null $date Date in YYYY-MM-DD format (uses current date if null)
     * @return TypedCollection<LogRecord> Collection of all log records from that date
     */
    public function execute(?string $date = null): TypedCollection
    {
        $results = new TypedCollection(LogRecord::class);
        $targetDate = $this->getTargetDate($date);
        $files = $this->pathService->getDayFiles($targetDate);

        foreach ($files as $fileInfo) {
            $this->streamFile($fileInfo->path, $results);
        }

        return $results;
    }

    /**
     * Determine the target date (current date if null provided).
     */
    private function getTargetDate(?string $date): string
    {
        return $date ?? date('Y-m-d');
    }

    /**
     * Stream all valid log records from a single file.
     *
     * @param string $filePath Path to the log file
     * @param TypedCollection<LogRecord> $results Collection to add records to
     */
    private function streamFile(string $filePath, TypedCollection $results): void
    {
        if (!$this->isFileReadable($filePath)) {
            return;
        }

        $handle = $this->openFile($filePath);
        if ($handle === false) {
            return;
        }

        $this->processFileLines($handle, $results);
        $this->closeFile($handle);
    }

    /**
     * Check if a file exists and is readable.
     */
    private function isFileReadable(string $filePath): bool
    {
        return file_exists($filePath);
    }

    /**
     * Open a file for reading.
     *
     * @return resource|false
     */
    private function openFile(string $filePath)
    {
        return fopen($filePath, 'r');
    }

    /**
     * Process all lines of a file and add valid records to results.
     *
     * @param resource $handle File handle
     * @param TypedCollection<LogRecord> $results Collection to add records to
     */
    private function processFileLines($handle, TypedCollection $results): void
    {
        while (($line = fgets($handle)) !== false) {
            $record = $this->deserializeLine($line);
            if ($record !== null) {
                $results->add($record);
            }
        }
    }

    /**
     * Deserialize a single JSON line to a LogRecord.
     */
    private function deserializeLine(string $line): ?LogRecord
    {
        return $this->serializer->deserialize($line);
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
