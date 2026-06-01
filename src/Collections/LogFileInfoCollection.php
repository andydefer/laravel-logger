<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Collections;

use AndyDefer\Directive\Collections\AbstractItemCollection;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Logger\Records\LogFileInfoRecord;

/**
 * Collection for LogFileInfoRecord instances.
 *
 * @extends AbstractTypedCollection<LogFileInfoRecord>
 */
final class LogFileInfoCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(LogFileInfoRecord::class);
    }
}
