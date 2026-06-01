<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tasks;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\Logger\Collections\LogDateCollection;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use InvalidArgumentException;

class QueryLogsTask
{
    public function __construct(
        private readonly LogPathService $pathService,
        private readonly LogSerializerService $serializer,
    ) {}

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

    private function searchFile(string $filePath, LogQueryRecord $query, TypedCollection $results): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            try {
                $record = $this->serializer->deserialize($line);
                if ($record === null) {
                    continue;
                }

                if ($this->matchesQuery($record, $query)) {
                    $results->add($record);
                }
            } catch (InvalidArgumentException $e) {
                // Log invalide, on l'ignore silencieusement
                continue;
            }
        }

        fclose($handle);
    }

    private function matchesQuery(LogRecord $record, LogQueryRecord $query): bool
    {
        if ($query->type !== null && $record->data->type !== $query->type) {
            return false;
        }

        if ($query->level !== null && $record->level !== $query->level) {
            return false;
        }

        if ($query->from !== null && $record->time < $query->from) {
            return false;
        }

        if ($query->to !== null && $record->time > $query->to) {
            return false;
        }

        return true;
    }
}
