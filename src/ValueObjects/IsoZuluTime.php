<?php

declare(strict_types=1);

namespace AndyDefer\Logger\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\Logger\Records\IsoZuluTimeRecord;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Value Object for ISO 8601 Zulu (UTC) timestamps.
 *
 * Represents a timestamp in the format YYYY-MM-DDTHH:MM:SSZ.
 * Ensures all timestamps are valid, normalized, and in UTC timezone.
 *
 * @author Andy Defer
 */
final class IsoZuluTime extends AbstractValueObject
{
    private const FORMAT = 'Y-m-d\TH:i:s\Z';
    private const LEGACY_FORMAT = 'Y-m-d H:i:s';

    private DateTimeImmutable $dateTime;

    /**
     * Create a new IsoZuluTime instance.
     *
     * @param string $value ISO 8601 Zulu timestamp (e.g., "2024-01-01T12:00:00Z")
     * @throws InvalidArgumentException If the timestamp format is invalid
     */
    public function __construct(string $value)
    {
        $this->dateTime = $this->parseTimestamp($value);
    }

    /**
     * Get the timestamp value.
     *
     * @return string Formatted timestamp (YYYY-MM-DDTHH:MM:SSZ)
     */
    public function getValue(): string
    {
        return $this->dateTime->format(self::FORMAT);
    }

    /**
     * Get the underlying DateTimeImmutable instance.
     *
     * @return DateTimeImmutable DateTime object for calculations
     */
    public function getDateTime(): DateTimeImmutable
    {
        return $this->dateTime;
    }

    /**
     * Check if this timestamp is after another.
     */
    public function isAfter(self $other): bool
    {
        return $this->dateTime > $other->dateTime;
    }

    /**
     * Check if this timestamp is before another.
     */
    public function isBefore(self $other): bool
    {
        return $this->dateTime < $other->dateTime;
    }

    /**
     * Check if this timestamp is equal to another.
     */
    public function isEqual(self $other): bool
    {
        return $this->dateTime == $other->dateTime;
    }

    /**
     * Add a number of seconds to the timestamp.
     *
     * @return self New instance with added seconds
     */
    public function addSeconds(int $seconds): self
    {
        $newDateTime = $this->dateTime->modify("+{$seconds} seconds");
        return new self($newDateTime->format(self::FORMAT));
    }

    /**
     * Add a number of minutes to the timestamp.
     *
     * @return self New instance with added minutes
     */
    public function addMinutes(int $minutes): self
    {
        $newDateTime = $this->dateTime->modify("+{$minutes} minutes");
        return new self($newDateTime->format(self::FORMAT));
    }

    /**
     * Add a number of hours to the timestamp.
     *
     * @return self New instance with added hours
     */
    public function addHours(int $hours): self
    {
        $newDateTime = $this->dateTime->modify("+{$hours} hours");
        return new self($newDateTime->format(self::FORMAT));
    }

    /**
     * Get the hour component (0-23).
     */
    public function getHour(): int
    {
        return (int) $this->dateTime->format('H');
    }

    /**
     * Get the date component (YYYY-MM-DD).
     */
    public function getDate(): string
    {
        return $this->dateTime->format('Y-m-d');
    }

    /**
     * Parse and validate a timestamp string.
     *
     * @param string $value Timestamp to parse
     * @return DateTimeImmutable Parsed datetime
     * @throws InvalidArgumentException If the format is invalid
     */
    private function parseTimestamp(string $value): DateTimeImmutable
    {
        // Try ISO 8601 Zulu format first
        $dateTime = DateTimeImmutable::createFromFormat(self::FORMAT . '+', $value . '+');

        if ($dateTime !== false) {
            return $dateTime->setTimezone(new \DateTimeZone('UTC'));
        }

        // Try legacy format (Y-m-d H:i:s)
        $dateTime = DateTimeImmutable::createFromFormat(self::LEGACY_FORMAT, $value);

        if ($dateTime !== false) {
            return $dateTime->setTimezone(new \DateTimeZone('UTC'));
        }

        // Try to parse any ISO 8601 string
        try {
            $dateTime = new DateTimeImmutable($value);

            if ($dateTime !== false) {
                return $dateTime->setTimezone(new \DateTimeZone('UTC'));
            }
        } catch (\Exception) {
            // Fall through to exception
        }

        throw new InvalidArgumentException(
            sprintf(
                'Invalid ISO 8601 Zulu timestamp: "%s". Expected format: YYYY-MM-DDTHH:MM:SSZ',
                $value
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getValueObject(): IsoZuluTimeRecord
    {
        return new IsoZuluTimeRecord(
            value: $this->getValue(),
            year: (int) $this->dateTime->format('Y'),
            month: (int) $this->dateTime->format('m'),
            day: (int) $this->dateTime->format('d'),
            hour: (int) $this->dateTime->format('H'),
            minute: (int) $this->dateTime->format('i'),
            second: (int) $this->dateTime->format('s'),
        );
    }
}
