<?php

declare(strict_types=1);

namespace AndyDefer\Logger;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\LaravelJsonl\Records\LogJsonlRecord;
use AndyDefer\LaravelJsonl\Records\TemporalLogQueryRecord;
use AndyDefer\Logger\Collections\LogRecordCollection;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Structured logger service using JSONL storage.
 *
 * Provides methods for writing logs at different severity levels,
 * querying existing logs, and streaming log files. Buffer mode
 * accumulates logs in memory for better performance.
 *
 * @author Andy Defer
 */
final class LoggerService implements LoggerInterface
{
    public function __construct(
        private readonly JsonlService $jsonlService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function log(LogRecord $record): void
    {
        $this->writeFromLogRecord($record);
    }

    /**
     * {@inheritDoc}
     */
    public function info(AbstractRecord $data): void
    {
        $this->write(LogLevel::INFO, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function warning(AbstractRecord $data): void
    {
        $this->write(LogLevel::WARNING, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function error(AbstractRecord $data): void
    {
        $this->write(LogLevel::ERROR, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function debug(AbstractRecord $data): void
    {
        $this->write(LogLevel::DEBUG, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function query(LogQueryRecord $query): AbstractTypedCollection
    {
        $jsonlQuery = new TemporalLogQueryRecord(
            from: new DateTimeVO($query->from->getValue()),
            to: new DateTimeVO($query->to->getValue()),
            type: $query->type,
            level: $query->level?->value,
        );

        $files = $this->jsonlService->getFilesToScan($jsonlQuery);
        $results = new LogRecordCollection;

        foreach ($files as $file) {
            if (! $this->jsonlService->fileExists($file)) {
                continue;
            }

            $lines = $this->jsonlService->search($file, function ($line) use ($query) {
                return $this->matchesQuery($line, $query);
            });

            foreach ($lines as $line) {
                $results->add($this->hydrateToLogRecord($line));
            }
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function stream(?string $date = null): AbstractTypedCollection
    {
        $targetDate = $date ?? date('Y-m-d');
        $results = new LogRecordCollection;
        $basePath = $this->jsonlService->getBaseDirectory();

        for ($hour = 0; $hour <= 23; $hour++) {
            $hourStr = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $filePath = implode(DIRECTORY_SEPARATOR, [
                rtrim($basePath, DIRECTORY_SEPARATOR),
                $targetDate,
                $hourStr.'.jsonl',
            ]);

            if (! $this->jsonlService->fileExists($filePath)) {
                continue;
            }

            $lines = $this->jsonlService->readAll($filePath);
            foreach ($lines as $line) {
                $results->add($this->hydrateToLogRecord($line));
            }
        }

        return $results;
    }

    /**
     * Enable buffering mode for better write performance.
     *
     * @param  int  $size  Number of records to buffer before auto-flush
     * @return self Returns the instance for method chaining
     */
    public function enableBuffer(int $size = 100): self
    {
        $this->jsonlService->enableBuffer($size);

        return $this;
    }

    /**
     * Disable buffering mode and flush any pending records.
     *
     * @return self Returns the instance for method chaining
     */
    public function disableBuffer(): self
    {
        $this->jsonlService->disableBuffer();

        return $this;
    }

    /**
     * Flush any pending records to disk immediately.
     */
    public function flush(): void
    {
        $this->jsonlService->flushBuffer();
    }

    /**
     * Check if buffering mode is currently enabled.
     */
    public function isBufferEnabled(): bool
    {
        return $this->jsonlService->isBufferEnabled();
    }

    /**
     * Get the current buffer size (0 if buffering is disabled).
     */
    public function getBufferSize(): int
    {
        return $this->jsonlService->getBufferSize();
    }

    /**
     * Write a log entry from level and data.
     */
    private function write(LogLevel $level, AbstractRecord $data): void
    {
        $record = $this->createLogJsonlRecord($level, $data);

        if ($this->jsonlService->isBufferEnabled()) {
            $this->jsonlService->writeBuffered($record);
        } else {
            $this->jsonlService->write($record);
        }
    }

    /**
     * Write a log entry from a LogRecord.
     */
    private function writeFromLogRecord(LogRecord $record): void
    {
        $jsonlRecord = new LogJsonlRecord(
            time: new DateTimeVO($record->time->getValue()),
            level: $record->level->value,
            type: $record->data->type,
            payload: $record->data->payload,
        );

        if ($this->jsonlService->isBufferEnabled()) {
            $this->jsonlService->writeBuffered($jsonlRecord);
        } else {
            $this->jsonlService->write($jsonlRecord);
        }
    }

    /**
     * Create a LogJsonlRecord from level and data.
     */
    private function createLogJsonlRecord(LogLevel $level, AbstractRecord $data): LogJsonlRecord
    {
        /** @var LogDataRecord $data */
        return new LogJsonlRecord(
            time: new DateTimeVO,
            level: $level->value,
            type: $data->type,
            payload: $data->payload,
        );
    }

    /**
     * Check if a line matches the query criteria.
     */
    private function matchesQuery(array $line, LogQueryRecord $query): bool
    {
        if ($query->type !== null && ($line['type'] ?? null) !== $query->type) {
            return false;
        }

        if ($query->level !== null && ($line['level'] ?? null) !== $query->level->value) {
            return false;
        }

        return true;
    }

    /**
     * Hydrate a LogRecord from array data.
     */
    private function hydrateToLogRecord(array $data): LogRecord
    {
        // ✅ Correction : Le payload est directement dans $data['payload']
        $payloadObject = StrictDataObject::from($data['payload'] ?? []);
        $type = $data['type'];

        $logData = LogDataRecord::from([
            'type' => $type,
            'payload' => $payloadObject,
        ]);

        $time = IsoZuluTime::from($data['time']);
        $level = LogLevel::tryFrom($data['level']);

        return new LogRecord(
            time: $time,
            level: $level,
            data: $logData,
        );
    }
}
