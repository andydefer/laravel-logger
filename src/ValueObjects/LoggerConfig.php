<?php

declare(strict_types=1);

namespace AndyDefer\Logger\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\Logger\Records\LoggerConfigRecord;
use InvalidArgumentException;

/**
 * Value Object representing the logger configuration.
 *
 * This immutable value object provides a single way to create instances:
 * using the static from() method inherited from AbstractValueObject.
 * Use getValue() to obtain the underlying record.
 *
 * @author Andy Defer
 */
final class LoggerConfig extends AbstractValueObject
{
    public function __construct(
        private readonly string $basePath,
        private readonly int $retentionDays,
    ) {
        $this->validate();
    }

    /**
     * Validates the configuration values.
     *
     * @throws InvalidArgumentException If validation fails
     */
    private function validate(): void
    {
        if ($this->basePath === '') {
            throw new InvalidArgumentException('Base path cannot be empty');
        }

        if ($this->retentionDays < 1) {
            throw new InvalidArgumentException(
                sprintf('Retention days must be at least 1, got %d', $this->retentionDays)
            );
        }
    }

    /**
     * Creates a new configuration with a different base path.
     */
    public function withBasePath(string $basePath): self
    {
        return new self(
            basePath: $basePath,
            retentionDays: $this->retentionDays,
        );
    }

    /**
     * Creates a new configuration with different retention days.
     */
    public function withRetentionDays(int $days): self
    {
        return new self(
            basePath: $this->basePath,
            retentionDays: $days,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getValue(): LoggerConfigRecord
    {
        return new LoggerConfigRecord(
            basePath: $this->basePath,
            retentionDays: $this->retentionDays,
        );
    }
}
