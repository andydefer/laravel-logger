<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\Logger\Collections\LogDateCollection;
use AndyDefer\Logger\Collections\LogFileInfoCollection;
use AndyDefer\Logger\Records\DateRangeRecord;
use AndyDefer\Logger\Records\LogFileInfoRecord;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use AndyDefer\Logger\ValueObjects\LogDate;
use AndyDefer\Logger\ValueObjects\LoggerConfig;

/**
 * Service for managing log file paths and file system operations.
 *
 * Handles file path generation, directory scanning, file information retrieval,
 * and log cleanup operations.
 *
 * @author Andy Defer
 */
final class LogPathService
{
    private LoggerConfig $config;

    /**
     * Create a new log path service.
     *
     * @param LoggerConfig|null $config Configuration for log storage (uses default if null)
     */
    public function __construct(?LoggerConfig $config = null)
    {
        $this->config = $config ?? LoggerConfig::from([
            'basePath' => storage_path('logs/structured'),
            'retentionDays' => 30,
        ]);
    }

    /**
     * Get the current logger configuration.
     */
    public function getConfig(): LoggerConfig
    {
        return $this->config;
    }

    /**
     * Generate the file path for a log entry based on its timestamp.
     *
     * Format: {basePath}/{YYYY-MM-DD}/{HH}-{HH+1}.jsonl
     * Hours are bucketed in pairs: 00-01, 01-02, ..., 23-00
     *
     * @param IsoZuluTime $timestamp ISO 8601 Zulu timestamp
     * @return string Absolute file path
     */
    public function getHourlyFilePath(IsoZuluTime $timestamp): string
    {
        $date = $timestamp->getDate();
        $hour = $timestamp->getHour();
        $hourRange = $this->getHourRange($hour);

        return $this->config->basePath . '/' . $date . '/' . $hourRange . '.jsonl';
    }

    /**
     * Get all log files for a specific date.
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return LogFileInfoCollection Collection of file information records
     */
    public function getDayFiles(string $date): LogFileInfoCollection
    {
        $results = new LogFileInfoCollection;
        $dayPath = $this->config->basePath . '/' . $date;

        if (!$this->isDirectoryReadable($dayPath)) {
            return $results;
        }

        $files = $this->getJsonlFilesInDirectory($dayPath);

        foreach ($files as $filePath) {
            $results->add($this->createFileInfoRecord($date, $filePath));
        }

        return $results;
    }

    /**
     * Get a collection of dates within a range.
     *
     * @param IsoZuluTime|null $from Start date (null for retention-based start)
     * @param IsoZuluTime|null $to End date (null for today)
     * @return LogDateCollection Collection of LogDate objects
     */
    public function getDateRange(?IsoZuluTime $from, ?IsoZuluTime $to): LogDateCollection
    {
        $dates = new LogDateCollection;
        $startDate = $this->determineStartDate($from);
        $endDate = $this->determineEndDate($to);

        if ($this->isDateRangeInvalid($startDate, $endDate)) {
            return $dates;
        }

        $current = $startDate;

        while ($this->isDateWithinRange($current, $endDate)) {
            $dates->add($current);
            $current = $current->addDays(1);
        }

        return $dates;
    }

    /**
     * Get date range with additional metadata.
     *
     * @param IsoZuluTime|null $from Start date
     * @param IsoZuluTime|null $to End date
     * @return DateRangeRecord Record containing start, end, and date collection
     */
    public function getDateRangeWithInfo(?IsoZuluTime $from, ?IsoZuluTime $to): DateRangeRecord
    {
        $dates = $this->getDateRange($from, $to);
        $start = $dates->first()?->getValue() ?? '';
        $end = $dates->last()?->getValue() ?? '';

        return new DateRangeRecord(
            start: $start,
            end: $end,
            dates: $dates,
        );
    }

    /**
     * List all log files across all dates.
     *
     * @return LogFileInfoCollection Complete collection of all log files
     */
    public function listAllLogFiles(): LogFileInfoCollection
    {
        $results = new LogFileInfoCollection;

        if (!$this->isDirectoryReadable($this->config->basePath)) {
            return $results;
        }

        $dateDirectories = $this->getDateDirectories();

        foreach ($dateDirectories as $datePath) {
            $date = basename($datePath);
            $dayFiles = $this->getDayFiles($date);

            foreach ($dayFiles as $fileInfo) {
                $results->add($fileInfo);
            }
        }

        return $results;
    }

    /**
     * Clean up old log files based on configured retention days.
     *
     * @return int Number of files deleted
     */
    public function cleanupOldLogs(): int
    {
        $deletedCount = 0;
        $cutoffDate = $this->calculateCutoffDate();

        $allFiles = $this->listAllLogFiles();

        foreach ($allFiles as $fileInfo) {
            if ($this->isFileOlderThanCutoff($fileInfo, $cutoffDate)) {
                if ($this->deleteFile($fileInfo->path)) {
                    $deletedCount++;
                }
            }
        }

        $this->removeEmptyDateDirectories();

        return $deletedCount;
    }

    /**
     * Count the number of lines in a file.
     *
     * @param string $filePath Path to the file
     * @return int Number of lines (0 if file cannot be read)
     */
    private function countFileLines(string $filePath): int
    {
        $lineCount = 0;
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return 0;
        }

        while (fgets($handle) !== false) {
            $lineCount++;
        }

        fclose($handle);

        return $lineCount;
    }

    /**
     * Get the hour range string for a given hour.
     *
     * @param int $hour Hour (0-23)
     * @return string Format "HH-HH" (e.g., "13-14", "23-00")
     */
    private function getHourRange(int $hour): string
    {
        $nextHour = ($hour + 1) % 24;
        return sprintf('%02d-%02d', $hour, $nextHour);
    }

    /**
     * Check if a directory exists and is readable.
     */
    private function isDirectoryReadable(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * Get all JSONL files in a directory, sorted alphabetically.
     *
     * @return array<string> List of file paths
     */
    private function getJsonlFilesInDirectory(string $directory): array
    {
        $files = glob($directory . '/*.jsonl');
        sort($files);
        return $files;
    }

    /**
     * Create a LogFileInfoRecord from a file path.
     */
    private function createFileInfoRecord(string $date, string $filePath): LogFileInfoRecord
    {
        $hour = basename($filePath, '.jsonl');
        $size = filesize($filePath);
        $lines = $this->countFileLines($filePath);

        return new LogFileInfoRecord(
            date: $date,
            hour: $hour,
            path: $filePath,
            size: $size,
            lines: $lines,
        );
    }

    /**
     * Determine the start date for a date range query.
     */
    private function determineStartDate(?IsoZuluTime $from): LogDate
    {
        if ($from !== null) {
            return LogDate::from(['value' => $from->getDate()]);
        }

        $cutoffDate = date('Y-m-d', strtotime('-' . $this->config->retentionDays . ' days'));
        return LogDate::from(['value' => $cutoffDate]);
    }

    /**
     * Determine the end date for a date range query.
     */
    private function determineEndDate(?IsoZuluTime $to): LogDate
    {
        if ($to !== null) {
            return LogDate::from(['value' => $to->getDate()]);
        }

        return LogDate::from(['value' => date('Y-m-d')]);
    }

    /**
     * Check if a date range is invalid (start after end).
     */
    private function isDateRangeInvalid(LogDate $start, LogDate $end): bool
    {
        return $start->isAfter($end);
    }

    /**
     * Check if a date is within the inclusive range [start, end].
     */
    private function isDateWithinRange(LogDate $current, LogDate $end): bool
    {
        return $current->isBefore($end) || $current->isEqual($end);
    }

    /**
     * Get all date directories in the base path.
     *
     * @return array<string> List of directory paths
     */
    private function getDateDirectories(): array
    {
        return glob($this->config->basePath . '/*', GLOB_ONLYDIR);
    }

    /**
     * Calculate the cutoff date based on retention days.
     */
    private function calculateCutoffDate(): LogDate
    {
        $cutoffDate = date('Y-m-d', strtotime('-' . $this->config->retentionDays . ' days'));
        return LogDate::from(['value' => $cutoffDate]);
    }

    /**
     * Check if a file is older than the cutoff date.
     */
    private function isFileOlderThanCutoff(LogFileInfoRecord $fileInfo, LogDate $cutoff): bool
    {
        $fileDate = LogDate::from(['value' => $fileInfo->date]);
        return $fileDate->isBefore($cutoff);
    }

    /**
     * Delete a file from the filesystem.
     */
    private function deleteFile(string $path): bool
    {
        return unlink($path);
    }

    /**
     * Remove empty date directories after cleanup.
     */
    private function removeEmptyDateDirectories(): void
    {
        $dateDirectories = $this->getDateDirectories();

        foreach ($dateDirectories as $datePath) {
            if ($this->isDirectoryEmpty($datePath)) {
                $this->removeDirectory($datePath);
            }
        }
    }

    /**
     * Check if a directory contains no JSONL files.
     */
    private function isDirectoryEmpty(string $directoryPath): bool
    {
        return count(glob($directoryPath . '/*.jsonl')) === 0;
    }

    /**
     * Remove a directory from the filesystem.
     */
    private function removeDirectory(string $path): void
    {
        rmdir($path);
    }
}
