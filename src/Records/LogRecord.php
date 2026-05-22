<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Recordable;

final class LogRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $time,
        public readonly LogLevel $level,
        public readonly Recordable $data,
    ) {}
}
