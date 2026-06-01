<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Contracts;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;

interface LoggerInterface
{
    public function log(LogRecord $record): void;

    public function info(AbstractRecord $data): void;

    public function warning(AbstractRecord $data): void;

    public function error(AbstractRecord $data): void;

    public function debug(AbstractRecord $data): void;

    public function query(LogQueryRecord $query): TypedCollection;

    public function stream(?string $date = null): TypedCollection;
}
