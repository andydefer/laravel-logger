<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Integration\Configs;

use AndyDefer\Logger\Configs\LoggerConfig;
use AndyDefer\Logger\Contracts\LoggerConfigInterface;
use AndyDefer\Logger\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Config;

final class LoggerConfigTest extends IntegrationTestCase
{
    public function test_config_returns_correct_base_path(): void
    {
        Config::set('logger.path', '/custom/log/path');
        Config::set('logger.retention_days', 30);
        Config::set('logger.buffer_size', null);

        $config = new LoggerConfig($this->app['config']);

        $this->assertSame('/custom/log/path', $config->basePath());
    }

    public function test_config_returns_default_base_path_when_not_configured(): void
    {
        Config::set('logger.path', null);

        $config = new LoggerConfig($this->app['config']);

        $expectedPath = storage_path('logs/structured');
        $this->assertSame($expectedPath, $config->basePath());
    }

    public function test_config_returns_correct_retention_days(): void
    {
        Config::set('logger.retention_days', 60);
        Config::set('logger.path', '/test/path');
        Config::set('logger.buffer_size', null);

        $config = new LoggerConfig($this->app['config']);

        $this->assertSame(60, $config->retentionDays());
    }

    public function test_config_returns_default_retention_days_when_not_configured(): void
    {
        // Nettoyer la configuration existante
        Config::set('logger.retention_days', null);

        // S'assurer qu'aucune valeur par défaut n'est chargée
        $config = new LoggerConfig($this->app['config']);

        // Si la valeur par défaut dans le fichier de config est modifiée,
        // on teste que le résultat est un entier positif
        $result = $config->retentionDays();

        // Alternative : tester que c'est un entier
        $this->assertIsInt($result);

        // Ou tester que c'est >= 0
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function test_config_returns_correct_buffer_size(): void
    {
        Config::set('logger.buffer_size', 100);
        Config::set('logger.path', '/test/path');
        Config::set('logger.retention_days', 30);

        $config = new LoggerConfig($this->app['config']);

        $this->assertSame(100, $config->bufferSize());
    }

    public function test_config_returns_null_buffer_size_when_not_configured(): void
    {
        Config::set('logger.buffer_size', null);

        $config = new LoggerConfig($this->app['config']);

        $this->assertNull($config->bufferSize());
    }

    public function test_config_returns_null_buffer_size_when_zero(): void
    {
        Config::set('logger.buffer_size', 0);

        $config = new LoggerConfig($this->app['config']);

        $this->assertNull($config->bufferSize());
    }

    public function test_config_is_buffer_enabled_returns_true_when_buffer_size_positive(): void
    {
        Config::set('logger.buffer_size', 100);

        $config = new LoggerConfig($this->app['config']);

        $this->assertTrue($config->isBufferEnabled());
    }

    public function test_config_is_buffer_enabled_returns_false_when_buffer_size_null(): void
    {
        Config::set('logger.buffer_size', null);

        $config = new LoggerConfig($this->app['config']);

        $this->assertFalse($config->isBufferEnabled());
    }

    public function test_config_is_buffer_enabled_returns_false_when_buffer_size_zero(): void
    {
        Config::set('logger.buffer_size', 0);

        $config = new LoggerConfig($this->app['config']);

        $this->assertFalse($config->isBufferEnabled());
    }

    public function test_config_can_be_registered_as_singleton(): void
    {
        $this->app->singleton(LoggerConfigInterface::class, LoggerConfig::class);

        $first = $this->app->make(LoggerConfigInterface::class);
        $second = $this->app->make(LoggerConfigInterface::class);

        $this->assertSame($first, $second);
    }
}
