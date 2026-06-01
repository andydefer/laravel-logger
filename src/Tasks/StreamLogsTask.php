<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tasks;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;

class StreamLogsTask
{
    public function __construct(
        private readonly LogPathService $pathService,
        private readonly LogSerializerService $serializer,
    ) {}

    public function execute(?string $date = null): TypedCollection
    {
        $results = new TypedCollection(LogRecord::class);

        $targetDate = $date ?? date('Y-m-d');
        $files = $this->pathService->getDayFiles($targetDate);

        foreach ($files as $fileInfo) {
            $this->streamFile($fileInfo->path, $results);
        }

        return $results;
    }

    private function streamFile(string $filePath, TypedCollection $results): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            $record = $this->serializer->deserialize($line);
            if ($record !== null) {
                $results->add($record);
            }
        }

        fclose($handle);
    }
}
