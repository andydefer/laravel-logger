<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Records\DateRangeRecord;
use AndyDefer\Logger\Records\LogFileInfoRecord;
use AndyDefer\Records\Collections\TypedCollection;

class LogPathService
{
    private LoggerConfig $config;

    public function __construct(?LoggerConfig $config = null)
    {
        $this->config = $config ?? LoggerConfig::default();
    }

    public function getBasePath(): string
    {
        return $this->config->basePath;
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

    public function getDayFiles(string $date): TypedCollection
    {
        $results = new TypedCollection(LogFileInfoRecord::class);
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

    public function getDateRange(?string $from, ?string $to): TypedCollection
    {
        $dates = new TypedCollection('string');

        if ($from === null) {
            $start = date('Y-m-d', strtotime('-' . $this->config->retentionDays . ' days'));
        } else {
            $start = substr($from, 0, 10);
        }

        if ($to === null) {
            $end = date('Y-m-d');
        } else {
            $end = substr($to, 0, 10);
        }

        $current = strtotime($start);
        $endTimestamp = strtotime($end);

        if ($current > $endTimestamp) {
            return $dates;
        }

        while ($current <= $endTimestamp) {
            $dates->add(date('Y-m-d', $current));
            $current = strtotime('+1 day', $current);
        }

        return $dates;
    }

    public function getDateRangeWithInfo(?string $from, ?string $to): DateRangeRecord
    {
        $dates = $this->getDateRange($from, $to);
        $dates->assertAllOfType('string');

        $start = $dates->firstItem() ?? '';
        $end = $dates->lastItem() ?? '';

        return new DateRangeRecord(
            start: $start,
            end: $end,
            dates: $dates,
        );
    }

    public function listAllLogFiles(): TypedCollection
    {
        $results = new TypedCollection(LogFileInfoRecord::class);

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
        $cutoffDate = date('Y-m-d', strtotime('-' . $this->config->retentionDays . ' days'));

        $allFiles = $this->listAllLogFiles();

        foreach ($allFiles as $fileInfo) {
            if ($fileInfo->date < $cutoffDate) {
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
