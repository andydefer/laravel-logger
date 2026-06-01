<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Integration\Config;

use AndyDefer\Logger\ValueObjects\LoggerConfig;
use AndyDefer\Logger\Tests\IntegrationTestCase;

final class LoggerConfigTest extends IntegrationTestCase
{

    public function test_with_base_path_returns_new_config_with_updated_base_path(): void
    {
        $original = new LoggerConfig('/default/path', 30);
        $newPath = '/custom/log/path';

        $updated = $original->withBasePath($newPath);

        $this->assertNotSame($original, $updated);
        $this->assertSame($newPath, $updated->basePath);
        $this->assertSame($original->retentionDays, $updated->retentionDays);
    }

    public function test_with_retention_days_returns_new_config_with_updated_retention_days(): void
    {
        $original = new LoggerConfig('/default/path', 30);
        $newRetentionDays = 60;

        $updated = $original->withRetentionDays($newRetentionDays);

        $this->assertNotSame($original, $updated);
        $this->assertSame($newRetentionDays, $updated->retentionDays);
        $this->assertSame($original->basePath, $updated->basePath);
    }

    public function test_chained_configuration_creates_correct_config(): void
    {
        $config = (new LoggerConfig('/default/path', 30))
            ->withBasePath('/custom/path')
            ->withRetentionDays(90);

        $this->assertSame('/custom/path', $config->basePath);
        $this->assertSame(90, $config->retentionDays);
    }

    public function test_to_array_returns_correct_representation(): void
    {
        $config = new LoggerConfig('/test/path', 45);

        $array = $config->getValue()->toArray();

        $this->assertSame('/test/path', $array['base_path']);
        $this->assertSame(45, $array['retention_days']);
    }
}
