<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\Records\AbstractRecord;

final class LogFilePathRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $path,
        public readonly string $date,
        public readonly string $hour,
    ) {}
}
