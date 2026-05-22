<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Records\AbstractRecord;

final class LogQueryRecord extends AbstractRecord
{
    /**
     * @param  string  $from  Date de début (ISO 8601 Zulu format, ex: 2026-04-05T00:00:00Z)
     * @param  string  $to  Date de fin (ISO 8601 Zulu format, ex: 2026-04-05T23:59:59Z)
     * @param  string|null  $type  Type d'événement (ex: 'user_login')
     * @param  LogLevel|null  $level  Niveau de log (DEBUG, INFO, WARNING, ERROR)
     */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly ?string $type = null,
        public readonly ?LogLevel $level = null,
    ) {}
}
