<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for the Nemesis package.
 *
 * Provides a consistent testing environment with:
 * - SQLite in-memory database for fast, isolated tests
 * - Frozen time (2024-01-01 12:00:00) for deterministic tests
 * - Package service provider registration
 * - Package-specific configuration defaults
 * - Migration loading from both package and test directories
 */
abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     *
     * Freezes time to a fixed moment to ensure test consistency
     * across all test cases.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }
}
