<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Config;

use AndyDefer\Records\AbstractRecord;

final class LoggerConfig extends AbstractRecord
{
    public function __construct(
        public readonly string $basePath,
        public readonly int $retentionDays = 30,
    ) {}

    public static function default(): self
    {
        return new self(
            basePath: storage_path('logs/structured'),
            retentionDays: 30,
        );
    }

    public function withBasePath(string $basePath): self
    {
        return new self(
            basePath: $basePath,
            retentionDays: $this->retentionDays,
        );
    }

    public function withRetentionDays(int $days): self
    {
        return new self(
            basePath: $this->basePath,
            retentionDays: $days,
        );
    }
}
