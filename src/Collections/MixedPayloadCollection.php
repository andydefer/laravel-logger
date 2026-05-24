<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Collections;

use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Collections\TypedCollection;
use stdClass;

/**
 * A specialized collection for log payloads that accepts mixed types.
 *
 * This collection is designed specifically for log payload data, allowing:
 * - Scalars: int, float, string, bool, null
 * - Records: Any class extending AbstractRecord
 * - Collections: Nested TypedCollection instances
 * - stdClass: Simple objects from JSON deserialization
 *
 * The collection provides convenience methods to check the nature of its contents
 * and a serialization method optimized for log output.
 *
 * @extends TypedCollection<int|float|string|bool|null|AbstractRecord|TypedCollection|stdClass>
 */
final class MixedPayloadCollection extends TypedCollection
{
    /**
     * Initializes the collection with all allowed log payload types.
     */
    public function __construct()
    {
        parent::__construct(
            'int',
            'float',
            'string',
            'bool',
            'null',
            AbstractRecord::class,
            TypedCollection::class,
            stdClass::class
        );
    }

    /**
     * Checks whether all items in the collection are scalar values.
     *
     * Scalar types include: int, float, string, bool, null.
     *
     * @return bool True if every item is a scalar, false otherwise
     */
    public function isAllScalars(): bool
    {
        return $this->scalars()->count() === $this->count();
    }

    /**
     * Checks whether all items in the collection are Record objects.
     *
     * @return bool True if every item extends AbstractRecord, false otherwise
     */
    public function isAllRecords(): bool
    {
        return $this->records()->count() === $this->count();
    }

    /**
     * Checks whether all items in the collection are stdClass objects.
     *
     * This is useful when working with JSON-deserialized data where
     * the original type information is lost.
     *
     * @return bool True if every item is a stdClass instance, false otherwise
     */
    public function isAllStdClass(): bool
    {
        $stdClassCount = 0;
        foreach ($this->items as $item) {
            if ($item instanceof stdClass) {
                $stdClassCount++;
            }
        }

        return $stdClassCount === $this->count();
    }
}
