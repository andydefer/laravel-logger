<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Contracts;

/**
 * Interface for logger configuration.
 *
 * Defines the contract for accessing logger configuration values.
 *
 * @author Andy Defer
 */
interface LoggerConfigInterface
{
    /**
     * Returns the base path where log files are stored.
     *
     * @return string Base directory path (e.g., '/var/logs/structured')
     */
    public function basePath(): string;

    /**
     * Returns the buffer size for log writes.
     *
     * @return int|null Number of records to buffer before auto-flush, or null if disabled
     */
    public function bufferSize(): ?int;

    /**
     * Returns the number of days to retain log files.
     *
     * @return int Number of days (e.g., 30)
     */
    public function retentionDays(): int;

    /**
     * Checks if buffering is enabled.
     *
     * @return bool True if buffer is enabled and size > 0
     */
    public function isBufferEnabled(): bool;
}
