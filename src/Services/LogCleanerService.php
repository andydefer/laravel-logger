<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\Records\Collections\TypedCollection;

final class LogCleanerService
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

        // Supprimer les dossiers vides
        $this->removeEmptyDirectories();

        return $deletedCount;
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

    public function getStats(): array
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

        return [
            'total_files' => $allFiles->count(),
            'total_days' => count($dates),
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'total_lines' => $totalLines,
            'oldest_date' => $allFiles->firstItem()?->date ?? null,
            'newest_date' => $allFiles->lastItem()?->date ?? null,
        ];
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
