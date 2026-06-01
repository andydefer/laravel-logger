<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\Logger\Collections\LogFileInfoCollection;
use AndyDefer\Logger\Records\LogFileInfoRecord;
use AndyDefer\Logger\Records\LogStatsRecord;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use AndyDefer\Logger\ValueObjects\LogDate;

/**
 * Service for cleaning and analyzing log files.
 *
 * Handles deletion of old log files, counting files to delete,
 * and generating statistics about log files.
 *
 * @author Andy Defer
 */
final class LogCleanerService
{
    public function __construct(
        private readonly LogPathService $pathService,
    ) {}

    /**
     * Clean old logs using the configured retention days.
     *
     * @return int Number of files deleted
     */
    public function clean(): int
    {
        return $this->pathService->cleanupOldLogs();
    }

    /**
     * Clean logs older than a specific cutoff date.
     *
     * @param IsoZuluTime $cutoffDate Cutoff date (any time on that day)
     * @return int Number of files deleted
     */
    public function cleanWithCutoff(IsoZuluTime $cutoffDate): int
    {
        $deletedCount = $this->deleteFilesOlderThan($cutoffDate);
        $this->removeEmptyDirectories();

        return $deletedCount;
    }

    /**
     * Count how many files would be deleted with a given cutoff date.
     *
     * @param IsoZuluTime $cutoffDate Cutoff date (any time on that day)
     * @return int Number of files that would be deleted
     */
    public function countFilesToDelete(IsoZuluTime $cutoffDate): int
    {
        $allFiles = $this->pathService->listAllLogFiles();
        $cutoff = LogDate::from(['value' => $cutoffDate->getDate()]);

        $count = 0;
        foreach ($allFiles as $fileInfo) {
            if ($this->isFileOlderThan($fileInfo, $cutoff)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get comprehensive statistics about all log files.
     *
     * @return LogStatsRecord Statistics including file count, size, lines, and date range
     */
    public function getStats(): LogStatsRecord
    {
        $allFiles = $this->pathService->listAllLogFiles();

        if ($allFiles->isEmpty()) {
            return $this->createEmptyStatsRecord();
        }

        $aggregates = $this->aggregateFileStats($allFiles);
        $oldestDate = $allFiles->first()?->date;
        $newestDate = $allFiles->last()?->date;

        return new LogStatsRecord(
            totalFiles: $allFiles->count(),
            totalDays: count($aggregates['dates']),
            totalSizeBytes: $aggregates['totalSize'],
            totalSizeMb: round($aggregates['totalSize'] / 1024 / 1024, 2),
            totalLines: $aggregates['totalLines'],
            oldestDate: $oldestDate,
            newestDate: $newestDate,
        );
    }

    /**
     * Get all log files for a specific date.
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return LogFileInfoCollection Collection of log file information
     */
    public function getFilesByDate(string $date): LogFileInfoCollection
    {
        return $this->pathService->getDayFiles($date);
    }

    /**
     * Calculate the total size of all log files in bytes.
     *
     * @return int Total size in bytes
     */
    public function getTotalSize(): int
    {
        $allFiles = $this->pathService->listAllLogFiles();
        $total = 0;

        foreach ($allFiles as $fileInfo) {
            $total += $fileInfo->size;
        }

        return $total;
    }

    /**
     * Delete all files older than the cutoff date.
     *
     * @param IsoZuluTime $cutoffDate Cutoff date
     * @return int Number of files deleted
     */
    private function deleteFilesOlderThan(IsoZuluTime $cutoffDate): int
    {
        $deletedCount = 0;
        $allFiles = $this->pathService->listAllLogFiles();
        $cutoff = LogDate::from(['value' => $cutoffDate->getDate()]);

        foreach ($allFiles as $fileInfo) {
            if ($this->isFileOlderThan($fileInfo, $cutoff)) {
                if ($this->deleteFile($fileInfo->path)) {
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Check if a file is older than the cutoff date.
     *
     * @param LogFileInfoRecord $fileInfo File information
     * @param LogDate $cutoff Cutoff date
     * @return bool True if file is older than cutoff
     */
    private function isFileOlderThan(LogFileInfoRecord $fileInfo, LogDate $cutoff): bool
    {
        $fileDate = LogDate::from(['value' => $fileInfo->date]);
        return $fileDate->isBefore($cutoff);
    }

    /**
     * Delete a single file from the filesystem.
     *
     * @param string $path File path
     * @return bool True if deletion was successful
     */
    private function deleteFile(string $path): bool
    {
        return unlink($path);
    }

    /**
     * Remove empty date directories after file deletion.
     */
    private function removeEmptyDirectories(): void
    {
        $basePath = $this->pathService->getConfig()->basePath;

        if (!is_dir($basePath)) {
            return;
        }

        $dateDirs = glob($basePath . '/*', GLOB_ONLYDIR);

        foreach ($dateDirs as $datePath) {
            if ($this->isDirectoryEmpty($datePath)) {
                $this->removeDirectory($datePath);
            }
        }
    }

    /**
     * Check if a directory contains no JSONL files.
     *
     * @param string $directoryPath Path to directory
     * @return bool True if directory has no JSONL files
     */
    private function isDirectoryEmpty(string $directoryPath): bool
    {
        return count(glob($directoryPath . '/*.jsonl')) === 0;
    }

    /**
     * Remove a directory from the filesystem.
     *
     * @param string $path Directory path
     */
    private function removeDirectory(string $path): void
    {
        rmdir($path);
    }

    /**
     * Aggregate statistics from a collection of log files.
     *
     * @param LogFileInfoCollection $files Collection of log files
     * @return array{totalSize: int, totalLines: int, dates: array<string, bool>}
     */
    private function aggregateFileStats(LogFileInfoCollection $files): array
    {
        $totalSize = 0;
        $totalLines = 0;
        $dates = [];

        foreach ($files as $fileInfo) {
            $totalSize += $fileInfo->size;
            $totalLines += $fileInfo->lines;
            $dates[$fileInfo->date] = true;
        }

        return [
            'totalSize' => $totalSize,
            'totalLines' => $totalLines,
            'dates' => $dates,
        ];
    }

    /**
     * Create an empty statistics record when no files exist.
     *
     * @return LogStatsRecord Statistics record with zeros and nulls
     */
    private function createEmptyStatsRecord(): LogStatsRecord
    {
        return new LogStatsRecord(
            totalFiles: 0,
            totalDays: 0,
            totalSizeBytes: 0,
            totalSizeMb: 0.0,
            totalLines: 0,
            oldestDate: null,
            newestDate: null,
        );
    }
}
