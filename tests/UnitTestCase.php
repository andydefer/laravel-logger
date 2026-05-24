<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for pure unit tests that don't need Laravel.
 * No Laravel bootstrap, no database, no migrations.
 *
 * ⚠️ RÈGLE : Les tests qui héritent de cette classe :
 * - NE PEUVENT PAS utiliser la base de données
 * - NE PEUVENT PAS utiliser les facades Laravel
 * - DOIVENT mocker toutes leurs dépendances
 */
abstract class UnitTestCase extends BaseTestCase
{
    protected function getDateRange(): array
    {
        return [
            'from' => '2024-01-01T00:00:00Z',
            'to' => '2024-01-01T23:59:59Z',
        ];
    }
}
