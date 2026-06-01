<?php

declare(strict_types=1);

namespace AndyDefer\Logger\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object representing a log file date.
 *
 * Encapsulates date logic for log file operations including formatting,
 * comparison, and date range generation.
 *
 * @author Andy Defer
 */
final class LogDate extends AbstractValueObject
{
    private const DATE_FORMAT = 'Y-m-d';

    private readonly \DateTimeImmutable $dateTime;

    public function __construct(
        private readonly string $value,
    ) {
        $this->validate();
        $this->dateTime = new \DateTimeImmutable($value);
    }

    private function validate(): void
    {
        if ($this->value === '') {
            throw new InvalidArgumentException('Date cannot be empty');
        }

        $date = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $this->value);
        if ($date === false || $date->format(self::DATE_FORMAT) !== $this->value) {
            throw new InvalidArgumentException(
                sprintf('Invalid date format. Expected Y-m-d, got "%s"', $this->value)
            );
        }
    }

    /**
     * Returns the date as string in Y-m-d format.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the date as a DateTimeImmutable object.
     */
    public function getDateTime(): \DateTimeImmutable
    {
        return $this->dateTime;
    }

    /**
     * Adds a number of days to this date.
     *
     * @param  int  $days  Number of days to add
     */
    public function addDays(int $days): self
    {
        return new self($this->dateTime->modify("+{$days} days")->format(self::DATE_FORMAT));
    }

    /**
     * Checks if this date is earlier than another.
     */
    public function isBefore(LogDate $other): bool
    {
        return $this->dateTime < $other->dateTime;
    }

    /**
     * Checks if this date is later than another.
     */
    public function isAfter(LogDate $other): bool
    {
        return $this->dateTime > $other->dateTime;
    }

    /**
     * Checks if this date is equal to another.
     */
    public function isEqual(LogDate $other): bool
    {
        return $this->value === $other->value;
    }
}
