<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\Logger\Collections\LogFileInfoCollection;
use AndyDefer\Logger\Records\LogStatsRecord;
use AndyDefer\Logger\ValueObjects\LogDate;

/**
 * Service for cleaning and analyzing log files.
 *
 * Handles deletion of old log files, counting files to delete,
 * and generating statistics about log files.
 *
 * @author Andy Defer
 */
class LogCleanerService
{
    public function __construct(
        private readonly LogPathService $pathService,
    ) {}

    public function clean(): int
    {
        return $this->pathService->cleanupOldLogs();
    }

    public function cleanWithCutoff(string $cutoffDate): int
    {
        $deletedCount = 0;
        $allFiles = $this->pathService->listAllLogFiles();
        $cutoff = LogDate::from(['value' => $cutoffDate]);

        foreach ($allFiles as $fileInfo) {
            $fileDate = LogDate::from(['value' => $fileInfo->date]);
            if ($fileDate->isBefore($cutoff)) {
                if (unlink($fileInfo->path)) {
                    $deletedCount++;
                }
            }
        }

        $this->removeEmptyDirectories();

        return $deletedCount;
    }

    public function countFilesToDelete(string $cutoffDate): int
    {
        $count = 0;
        $allFiles = $this->pathService->listAllLogFiles();
        $cutoff = LogDate::from(['value' => $cutoffDate]);

        foreach ($allFiles as $fileInfo) {
            $fileDate = LogDate::from(['value' => $fileInfo->date]);
            if ($fileDate->isBefore($cutoff)) {
                $count++;
            }
        }

        return $count;
    }

    private function removeEmptyDirectories(): void
    {
        $basePath = $this->pathService->getConfig()->basePath;

        if (! is_dir($basePath)) {
            return;
        }

        $dateDirs = glob($basePath . '/*', GLOB_ONLYDIR);
        foreach ($dateDirs as $datePath) {
            if (count(glob($datePath . '/*.jsonl')) === 0) {
                rmdir($datePath);
            }
        }
    }

    public function getStats(): LogStatsRecord
    {
        $allFiles = $this->pathService->listAllLogFiles();

        $totalSize = 0;
        $totalLines = 0;
        $dates = [];

        foreach ($allFiles as $fileInfo) {
            $totalSize += $fileInfo->size;
            $totalLines += $fileInfo->lines;
            $dates[$fileInfo->date] = true;
        }

        $oldestDate = $allFiles->first()?->date;
        $newestDate = $allFiles->last()?->date;

        return new LogStatsRecord(
            totalFiles: $allFiles->count(),
            totalDays: count($dates),
            totalSizeBytes: $totalSize,
            totalSizeMb: round($totalSize / 1024 / 1024, 2),
            totalLines: $totalLines,
            oldestDate: $oldestDate,
            newestDate: $newestDate,
        );
    }

    public function getFilesByDate(string $date): LogFileInfoCollection
    {
        return $this->pathService->getDayFiles($date);
    }

    public function getTotalSize(): int
    {
        $allFiles = $this->pathService->listAllLogFiles();
        $total = 0;

        foreach ($allFiles as $fileInfo) {
            $total += $fileInfo->size;
        }

        return $total;
    }
}
