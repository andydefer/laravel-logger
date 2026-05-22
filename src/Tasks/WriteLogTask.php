<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tasks;

use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use RuntimeException;

class WriteLogTask
{
    public function __construct(
        private readonly LogPathService $pathService,
        private readonly LogSerializerService $serializer,
    ) {}

    public function execute(LogRecord $record): void
    {
        $filePath = $this->pathService->getHourlyFilePath($record->time);

        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            if (! mkdir($directory, 0755, true)) {
                throw new RuntimeException("Cannot create log directory: {$directory}");
            }
        }

        $jsonLine = $this->serializer->serialize($record);

        // Écriture avec verrouillage pour concurrence
        $handle = fopen($filePath, 'a');
        if ($handle === false) {
            throw new RuntimeException("Cannot open log file: {$filePath}");
        }

        if (flock($handle, LOCK_EX)) {
            fwrite($handle, $jsonLine);
            fflush($handle);
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }

    public function getFilePath(string $timestamp): string
    {
        return $this->pathService->getHourlyFilePath($timestamp);
    }

    public function serialize(LogRecord $record): string
    {
        return $this->serializer->serialize($record);
    }
}
