<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tasks;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\Logger\Collections\LogDateCollection;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

/**
 * Task for querying log records based on search criteria.
 *
 * Supports filtering by date range, log type, and log level.
 *
 * @author Andy Defer
 */
class QueryLogsTask
{
    public function __construct(
        private readonly LogPathService $pathService,
        private readonly LogSerializerService $serializer,
    ) {}

    /**
     * Execute a query and return matching log records.
     *
     * @param LogQueryRecord $query The query parameters
     * @return TypedCollection<LogRecord> Collection of matching records
     */
    public function execute(LogQueryRecord $query): TypedCollection
    {
        $results = new TypedCollection(LogRecord::class);
        $dateRange = $this->pathService->getDateRange($query->from, $query->to);
        $files = $this->getFilesFromDateRange($dateRange);

        foreach ($files as $filePath) {
            $this->searchFile($filePath, $query, $results);
        }

        return $results;
    }

    /**
     * Get all file paths from a date range.
     *
     * @param LogDateCollection $dateRange Collection of dates
     * @return TypedCollection<string> Collection of file paths
     */
    private function getFilesFromDateRange(LogDateCollection $dateRange): TypedCollection
    {
        $files = new TypedCollection('string');

        foreach ($dateRange as $date) {
            $dayFiles = $this->pathService->getDayFiles($date->getValue());

            foreach ($dayFiles as $fileInfo) {
                $files->add($fileInfo->path);
            }
        }

        return $files;
    }

    /**
     * Search a single file for records matching the query.
     *
     * @param string $filePath Path to the log file
     * @param LogQueryRecord $query The query parameters
     * @param TypedCollection<LogRecord> $results Collection to add matching records to
     */
    private function searchFile(string $filePath, LogQueryRecord $query, TypedCollection $results): void
    {
        if (!$this->isFileReadable($filePath)) {
            return;
        }

        $handle = $this->openFile($filePath);
        if ($handle === false) {
            return;
        }

        $this->processFileLines($handle, $query, $results);
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
     * Process all lines of a file and add matching records to results.
     *
     * @param resource $handle File handle
     * @param LogQueryRecord $query The query parameters
     * @param TypedCollection<LogRecord> $results Collection to add matching records to
     */
    private function processFileLines($handle, LogQueryRecord $query, TypedCollection $results): void
    {
        while (($line = fgets($handle)) !== false) {
            $record = $this->serializer->deserialize($line);
            if ($record === null) {
                continue;
            }

            if ($this->matchesQuery($record, $query)) {
                $results->add($record);
            }
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

    /**
     * Check if a record matches the query criteria.
     *
     * @param LogRecord $record The record to check
     * @param LogQueryRecord $query The query criteria
     * @return bool True if the record matches
     */
    private function matchesQuery(LogRecord $record, LogQueryRecord $query): bool
    {
        if (!$this->matchesType($record, $query)) {
            return false;
        }

        if (!$this->matchesLevel($record, $query)) {
            return false;
        }

        if (!$this->matchesDateRange($record, $query)) {
            return false;
        }

        return true;
    }

    /**
     * Check if record type matches query.
     */
    private function matchesType(LogRecord $record, LogQueryRecord $query): bool
    {
        if ($query->type === null) {
            return true;
        }

        return $record->data->type === $query->type;
    }

    /**
     * Check if record level matches query.
     */
    private function matchesLevel(LogRecord $record, LogQueryRecord $query): bool
    {
        if ($query->level === null) {
            return true;
        }

        return $record->level === $query->level;
    }

    /**
     * Check if record timestamp is within query date range.
     */
    private function matchesDateRange(LogRecord $record, LogQueryRecord $query): bool
    {
        if (!$this->matchesFromDate($record, $query)) {
            return false;
        }

        if (!$this->matchesToDate($record, $query)) {
            return false;
        }

        return true;
    }

    /**
     * Check if record is after or equal to the from date.
     */
    private function matchesFromDate(LogRecord $record, LogQueryRecord $query): bool
    {
        if ($query->from === null) {
            return true;
        }

        return !$record->time->isBefore($query->from);
    }

    /**
     * Check if record is before or equal to the to date.
     */
    private function matchesToDate(LogRecord $record, LogQueryRecord $query): bool
    {
        if ($query->to === null) {
            return true;
        }

        return !$record->time->isAfter($query->to);
    }
}
