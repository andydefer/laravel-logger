<?php

declare(strict_types=1);

namespace AndyDefer\Logger;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\LaravelJsonl\Strategies\TemporalPathStrategy;
use AndyDefer\LaravelJsonl\Contexts\JsonlContext;
use AndyDefer\Logger\Configs\LoggerConfig;
use AndyDefer\Logger\Contracts\LoggerConfigInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\Logger\Contracts\LoggerInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

final class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
        $this->registerHydrationService();
        $this->registerJsonlDependencies();
        $this->registerLoggerService();
    }

    private function registerConfig(): void
    {
        $this->app->singleton(LoggerConfigInterface::class, function ($app) {
            return new LoggerConfig($app->make(ConfigRepository::class));
        });
    }

    private function registerHydrationService(): void
    {
        $this->app->singleton(HydrationService::class);
    }

    private function registerJsonlDependencies(): void
    {
        $this->app->singleton(JsonlContext::class);
        $this->app->singleton(FileSystemService::class);

        $this->app->singleton(TemporalPathStrategy::class, function ($app) {
            $config = $app->make(LoggerConfigInterface::class);
            return new TemporalPathStrategy($config->basePath());
        });

        $this->app->singleton(JsonlService::class, function ($app) {
            $config = $app->make(LoggerConfigInterface::class);

            return new JsonlService(
                pathStrategy: $app->make(TemporalPathStrategy::class),
                fileSystem: $app->make(FileSystemService::class),
                context: $app->make(JsonlContext::class),
                defaultBufferSize: $config->bufferSize(),
            );
        });
    }

    private function registerLoggerService(): void
    {
        $this->app->singleton(LoggerService::class, function ($app) {
            return new LoggerService(
                jsonlService: $app->make(JsonlService::class),
                hydrationService: $app->make(HydrationService::class),
            );
        });

        $this->app->singleton(LoggerInterface::class, function ($app) {
            return $app->make(LoggerService::class);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/logger.php' => config_path('logger.php'),
        ], 'logger-config');
    }
}
