<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\Logger\Records\LogStatsRecord;
use AndyDefer\Records\Collections\TypedCollection;

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

        foreach ($allFiles as $fileInfo) {
            if ($fileInfo->date < $cutoffDate) {
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

        foreach ($allFiles as $fileInfo) {
            if ($fileInfo->date < $cutoffDate) {
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

        return new LogStatsRecord(
            totalFiles: $allFiles->count(),
            totalDays: count($dates),
            totalSizeBytes: $totalSize,
            totalSizeMb: round($totalSize / 1024 / 1024, 2),
            totalLines: $totalLines,
            oldestDate: $allFiles->firstItem()?->date ?? null,
            newestDate: $allFiles->lastItem()?->date ?? null,
        );
    }

    public function getFilesByDate(string $date): TypedCollection
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
