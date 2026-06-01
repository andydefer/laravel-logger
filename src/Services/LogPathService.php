<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\Logger\Collections\LogDateCollection;
use AndyDefer\Logger\Collections\LogFileInfoCollection;
use AndyDefer\Logger\Records\DateRangeRecord;
use AndyDefer\Logger\Records\LogFileInfoRecord;
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
class LogPathService
{
    private LoggerConfig $config;

    public function __construct(?LoggerConfig $config = null)
    {
        $this->config = $config ?? LoggerConfig::from([
            'basePath' => storage_path('logs/structured'),
            'retentionDays' => 30,
        ]);
    }

    public function getConfig(): LoggerConfig
    {
        return $this->config;
    }

    public function getHourlyFilePath(string $timestamp): string
    {
        $date = substr($timestamp, 0, 10);
        $hour = (int) substr($timestamp, 11, 2);
        $hourRange = $this->getHourRange($hour);

        return $this->config->basePath . '/' . $date . '/' . $hourRange . '.jsonl';
    }

    public function getDayFiles(string $date): LogFileInfoCollection
    {
        $results = new LogFileInfoCollection;
        $dayPath = $this->config->basePath . '/' . $date;

        if (! is_dir($dayPath)) {
            return $results;
        }

        $files = glob($dayPath . '/*.jsonl');
        sort($files);

        foreach ($files as $file) {
            $hour = basename($file, '.jsonl');
            $size = filesize($file);
            $lines = $this->countFileLines($file);

            $results->add(new LogFileInfoRecord(
                date: $date,
                hour: $hour,
                path: $file,
                size: $size,
                lines: $lines,
            ));
        }

        return $results;
    }

    public function getDateRange(?string $from, ?string $to): LogDateCollection
    {
        $dates = new LogDateCollection;

        $startDate = $from === null
            ? LogDate::from(['value' => date('Y-m-d', strtotime('-' . $this->config->retentionDays . ' days'))])
            : LogDate::from(['value' => substr($from, 0, 10)]);

        $endDate = $to === null
            ? LogDate::from(['value' => date('Y-m-d')])
            : LogDate::from(['value' => substr($to, 0, 10)]);

        $current = $startDate;

        if ($current->isAfter($endDate)) {
            return $dates;
        }

        while ($current->isBefore($endDate) || $current->isEqual($endDate)) {
            $dates->add($current);
            $current = $current->addDays(1);
        }

        return $dates;
    }

    public function getDateRangeWithInfo(?string $from, ?string $to): DateRangeRecord
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

    public function listAllLogFiles(): LogFileInfoCollection
    {
        $results = new LogFileInfoCollection;

        if (! is_dir($this->config->basePath)) {
            return $results;
        }

        $dateDirs = glob($this->config->basePath . '/*', GLOB_ONLYDIR);

        foreach ($dateDirs as $datePath) {
            $date = basename($datePath);
            $dayFiles = $this->getDayFiles($date);

            foreach ($dayFiles as $fileInfo) {
                $results->add($fileInfo);
            }
        }

        return $results;
    }

    public function cleanupOldLogs(): int
    {
        $deletedCount = 0;
        $cutoffDate = LogDate::from(['value' => date('Y-m-d', strtotime('-' . $this->config->retentionDays . ' days'))]);

        $allFiles = $this->listAllLogFiles();

        foreach ($allFiles as $fileInfo) {
            $fileDate = LogDate::from(['value' => $fileInfo->date]);
            if ($fileDate->isBefore($cutoffDate)) {
                if (unlink($fileInfo->path)) {
                    $deletedCount++;
                }
            }
        }

        $dateDirs = glob($this->config->basePath . '/*', GLOB_ONLYDIR);
        foreach ($dateDirs as $datePath) {
            if (count(glob($datePath . '/*.jsonl')) === 0) {
                rmdir($datePath);
            }
        }

        return $deletedCount;
    }

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

    private function getHourRange(int $hour): string
    {
        $nextHour = ($hour + 1) % 24;

        return sprintf('%02d-%02d', $hour, $nextHour);
    }
}
