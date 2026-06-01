<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests;

use AndyDefer\Logger\LoggerServiceProvider;
use Carbon\Carbon;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $app['config']->set('logger.path', '/tmp/logger_tests');
        $app['config']->set('logger.retention_days', 7);
    }

    protected function getPackageProviders($app)
    {
        return [
            LoggerServiceProvider::class,
        ];
    }
}
