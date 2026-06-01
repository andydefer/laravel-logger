<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Collections;

use AndyDefer\Directive\Collections\AbstractItemCollection;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Logger\ValueObjects\LogDate;

/**
 * Collection for LogDate value objects.
 *
 *
 * @extends AbstractTypedCollection<LogDate>
 */
final class LogDateCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(LogDate::class);
    }

    /**
     * Converts the collection to an array of string dates.
     *
     * @return array<string>
     */
    public function toStringArray(): array
    {
        return array_map(fn(LogDate $date) => $date->getValue(), $this->toArray());
    }
}
