<?php

declare(strict_types=1);

namespace AndyDefer\Logger;

use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogBufferService;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Recordable;

class Logger implements LoggerInterface
{
    private ?LogBufferService $buffer = null;

    private bool $useBuffer = false;

    public function __construct(
        private readonly WriteLogTask $writeLogTask,
        private readonly QueryLogsTask $queryLogsTask,
        private readonly StreamLogsTask $streamLogsTask,
    ) {}

    public function enableBuffer(int $size = 100): self
    {
        $this->useBuffer = true;
        $this->buffer = new LogBufferService($this->writeLogTask, $size);

        return $this;
    }

    public function disableBuffer(): self
    {
        if ($this->buffer !== null) {
            $this->buffer->flush();
        }
        $this->useBuffer = false;
        $this->buffer = null;

        return $this;
    }

    public function flush(): void
    {
        if ($this->buffer !== null) {
            $this->buffer->flush();
        }
    }

    public function isBufferEnabled(): bool
    {
        return $this->useBuffer;
    }

    public function getBufferSize(): int
    {
        return $this->buffer?->getBufferSize() ?? 0;
    }

    private function write(LogRecord $record): void
    {
        if ($this->useBuffer && $this->buffer !== null) {
            $this->buffer->push($record);
        } else {
            $this->writeLogTask->execute($record);
        }
    }

    public function log(LogRecord $record): void
    {
        $this->write($record);
    }

    public function info(Recordable $data): void
    {
        $this->write(new LogRecord(
            time: now()->toIso8601ZuluString(),
            level: LogLevel::INFO,
            data: $data,
        ));
    }

    public function warning(Recordable $data): void
    {
        $this->write(new LogRecord(
            time: now()->toIso8601ZuluString(),
            level: LogLevel::WARNING,
            data: $data,
        ));
    }

    public function error(Recordable $data): void
    {
        $this->write(new LogRecord(
            time: now()->toIso8601ZuluString(),
            level: LogLevel::ERROR,
            data: $data,
        ));
    }

    public function debug(Recordable $data): void
    {
        $this->write(new LogRecord(
            time: now()->toIso8601ZuluString(),
            level: LogLevel::DEBUG,
            data: $data,
        ));
    }

    public function query(LogQueryRecord $query): TypedCollection
    {
        // Vider le buffer avant query pour cohérence
        if ($this->buffer !== null) {
            $this->buffer->flush();
        }

        return $this->queryLogsTask->execute($query);
    }

    public function stream(?string $date = null): TypedCollection
    {
        if ($this->buffer !== null) {
            $this->buffer->flush();
        }

        return $this->streamLogsTask->execute($date);
    }
}
