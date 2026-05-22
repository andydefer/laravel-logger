<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Tasks\WriteLogTask;
use Closure;

final class LogBufferService
{
    private array $buffer = [];

    private int $bufferSize;

    private ?Closure $onFlush = null;

    public function __construct(
        private readonly WriteLogTask $writeTask,
        int $bufferSize = 100,
    ) {
        $this->bufferSize = $bufferSize;
    }

    public function push(LogRecord $record): void
    {
        $this->buffer[] = $record;

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        // Grouper par fichier pour optimiser
        $grouped = [];
        foreach ($this->buffer as $record) {
            $filePath = $this->writeTask->getFilePath($record->time);
            if (! isset($grouped[$filePath])) {
                $grouped[$filePath] = [];
            }
            $grouped[$filePath][] = $record;
        }

        foreach ($grouped as $filePath => $records) {
            $this->writeBatch($filePath, $records);
        }

        if ($this->onFlush !== null) {
            ($this->onFlush)(count($this->buffer));
        }

        $this->buffer = [];
    }

    private function writeBatch(string $filePath, array $records): void
    {
        $directory = dirname($filePath);

        // Validation du chemin pour éviter les erreurs
        if (empty($directory) || $directory === '/' || $directory === '.') {
            return;
        }

        if (! is_dir($directory)) {
            if (! @mkdir($directory, 0755, true)) {
                return;
            }
        }

        $handle = @fopen($filePath, 'a');
        if ($handle === false) {
            return;
        }

        if (flock($handle, LOCK_EX)) {
            foreach ($records as $record) {
                $jsonLine = $this->writeTask->serialize($record);
                @fwrite($handle, $jsonLine);
            }
            @fflush($handle);
            flock($handle, LOCK_UN);
        }
        @fclose($handle);
    }

    public function onFlush(Closure $callback): self
    {
        $this->onFlush = $callback;

        return $this;
    }

    public function size(): int
    {
        return count($this->buffer);
    }

    public function isDirty(): bool
    {
        return ! empty($this->buffer);
    }

    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    public function setBufferSize(int $size): self
    {
        $this->bufferSize = $size;
        if (count($this->buffer) >= $size) {
            $this->flush();
        }

        return $this;
    }

    public function __destruct()
    {
        $this->flush();
    }
}
