<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Configs;

use AndyDefer\Logger\Contracts\LoggerConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Laravel implementation of logger configuration.
 *
 * Reads configuration values from Laravel's config repository.
 *
 * @author Andy Defer
 */
final class LoggerConfig implements LoggerConfigInterface
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function basePath(): string
    {
        $path = $this->config->get('logger.path');

        if (is_string($path) && $path !== '') {
            return $path;
        }

        if (function_exists('storage_path')) {
            return storage_path('logs/structured');
        }

        return getcwd().DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'structured';
    }

    public function bufferSize(): ?int
    {
        $size = $this->config->get('logger.buffer_size');

        if ($size === null) {
            return null;
        }

        $intSize = (int) $size;

        return $intSize > 0 ? $intSize : null;
    }

    public function retentionDays(): int
    {
        $days = $this->config->get('logger.retention_days', 30);

        return (int) $days;
    }

    public function isBufferEnabled(): bool
    {
        return $this->bufferSize() !== null && $this->bufferSize() > 0;
    }
}
