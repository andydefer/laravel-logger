<?php

// vendor/andydefer/logger/src/LoggerService.php

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
use Throwable;

/**
 * Structured logger service using JSONL storage.
 *
 * **Non-blocking contract**: a logger must never break the application.
 * Every public write method catches every Throwable and swallows it.
 * Failures are recorded to PHP's error log only (best-effort).
 */
final class LoggerService implements LoggerInterface
{
    public function __construct(
        private readonly JsonlService $jsonlService,
    ) {}

    public function log(LogRecord $record): void
    {
        $this->safely(function () use ($record): void {
            $this->writeFromLogRecord($record);
        });
    }

    public function info(AbstractRecord $data): void
    {
        $this->safely(fn () => $this->write(LogLevel::INFO, $data));
    }

    public function warning(AbstractRecord $data): void
    {
        $this->safely(fn () => $this->write(LogLevel::WARNING, $data));
    }

    public function error(AbstractRecord $data): void
    {
        $this->safely(fn () => $this->write(LogLevel::ERROR, $data));
    }

    public function debug(AbstractRecord $data): void
    {
        $this->safely(fn () => $this->write(LogLevel::DEBUG, $data));
    }

    public function query(LogQueryRecord $query): AbstractTypedCollection
    {
        try {
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
        } catch (Throwable $e) {
            $this->reportFailure('query', $e);

            return new LogRecordCollection;
        }
    }

    public function stream(?string $date = null): AbstractTypedCollection
    {
        try {
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
        } catch (Throwable $e) {
            $this->reportFailure('stream', $e);

            return new LogRecordCollection;
        }
    }

    public function enableBuffer(int $size = 100): self
    {
        try {
            $this->jsonlService->enableBuffer($size);
        } catch (Throwable $e) {
            $this->reportFailure('enableBuffer', $e);
        }

        return $this;
    }

    public function disableBuffer(): self
    {
        try {
            $this->jsonlService->disableBuffer();
        } catch (Throwable $e) {
            $this->reportFailure('disableBuffer', $e);
        }

        return $this;
    }

    public function flush(): void
    {
        try {
            $this->jsonlService->flushBuffer();
        } catch (Throwable $e) {
            $this->reportFailure('flush', $e);
        }
    }

    public function isBufferEnabled(): bool
    {
        try {
            return $this->jsonlService->isBufferEnabled();
        } catch (Throwable $e) {
            $this->reportFailure('isBufferEnabled', $e);

            return false;
        }
    }

    public function getBufferSize(): int
    {
        try {
            return $this->jsonlService->getBufferSize();
        } catch (Throwable $e) {
            $this->reportFailure('getBufferSize', $e);

            return 0;
        }
    }

    // ============================================================
    // Safe wrappers
    // ============================================================

    /**
     * Executes the given callback, swallowing any Throwable.
     *
     * A logger must never break the application. Failures are recorded
     * to PHP's error log only, on a best-effort basis.
     */
    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            $this->reportFailure('write', $e);
        }
    }

    /**
     * Reports a logger failure without ever throwing.
     */
    private function reportFailure(string $operation, Throwable $e): void
    {
        try {
            error_log(sprintf(
                '[logger] %s failed: %s: %s',
                $operation,
                $e::class,
                $e->getMessage(),
            ));
        } catch (Throwable) {
            // even error_log can fail — ignore
        }
    }

    // ============================================================
    // Internal write methods (unchanged)
    // ============================================================

    private function write(LogLevel $level, AbstractRecord $data): void
    {
        $record = $this->createLogJsonlRecord($level, $data);

        if ($this->jsonlService->isBufferEnabled()) {
            $this->jsonlService->writeBuffered($record);
        } else {
            $this->jsonlService->write($record);
        }
    }

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

    private function hydrateToLogRecord(array $data): LogRecord
    {
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
