<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Logger\Enums\LogLevel;

final class LogRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $time,
        public readonly LogLevel $level,
        public readonly LogDataRecord $data,
    ) {}
}
