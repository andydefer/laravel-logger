<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Contracts;

use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Recordable;

interface LoggerInterface
{
    public function log(LogRecord $record): void;

    public function info(Recordable $data): void;

    public function warning(Recordable $data): void;

    public function error(Recordable $data): void;

    public function debug(Recordable $data): void;

    public function query(LogQueryRecord $query): TypedCollection;

    public function stream(?string $date = null): TypedCollection;
}
