<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Enums;

enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::DEBUG => 'Debug',
            self::INFO => 'Info',
            self::WARNING => 'Warning',
            self::ERROR => 'Error',
        };
    }

    public function isDebug(): bool
    {
        return $this === self::DEBUG;
    }

    public function isInfo(): bool
    {
        return $this === self::INFO;
    }

    public function isWarning(): bool
    {
        return $this === self::WARNING;
    }

    public function isError(): bool
    {
        return $this === self::ERROR;
    }
}
