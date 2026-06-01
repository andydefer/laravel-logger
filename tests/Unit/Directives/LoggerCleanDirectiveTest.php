<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Unit\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use AndyDefer\Logger\Directives\LoggerCleanDirective;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Test suite for LoggerCleanDirective.
 *
 * Validates directive signature, aliases, execution flow, and integration
 * with the log cleaning services.
 *
 * @author Andy Defer
 */
#[AllowMockObjectsWithoutExpectations]
final class LoggerCleanDirectiveTest extends UnitTestCase
{
    use InteractsWithDirectives;

    private LoggerCleanDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Initialize isolated testing environment with Laravel support
        $this->initDirectiveTesting(bootLaravel: true);

        // Arrange: Create directive with real services (no mocks needed due to isolation)
        $this->directive = new LoggerCleanDirective(
            interaction: $this->interaction,
            cleaner: new LogCleanerService(new LogPathService()),
            pathService: new LogPathService(),
            laravelBootstrapper: $this->directiveContainer->make(LaravelBootstrapper::class),
        );

        // Arrange: Register directive in the test registry
        $this->registerDirective($this->directive);
    }

    protected function tearDown(): void
    {
        // Clean up temporary testing environment
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    // ==================== METADATA TESTS ====================

    public function test_get_signature_returns_correct_signature(): void
    {
        // Act
        $signature = $this->directive->getSignature();

        // Assert
        $this->assertStringContainsString('logger-clean', $signature);
        $this->assertStringContainsString('--days=', $signature);
        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('--verbose', $signature);
    }

    public function test_get_description_returns_correct_description(): void
    {
        // Act
        $description = $this->directive->getDescription();

        // Assert
        $this->assertStringContainsString('Remove old log files', $description);
    }

    public function test_get_aliases_returns_correct_aliases(): void
    {
        // Act
        $aliases = $this->directive->getAliases();

        // Assert
        $this->assertInstanceOf(\AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection::class, $aliases);
        $this->assertTrue($aliases->contains('log-clean'));
        $this->assertTrue($aliases->contains('clean-logs'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_should_boot_laravel_returns_true(): void
    {
        // Act & Assert
        $this->assertTrue($this->directive->shouldBootLaravel());
    }

    // ==================== EXECUTION TESTS ====================

    public function test_execute_without_options_cleans_logs_and_returns_success(): void
    {
        // Act
        $response = $this->runDirective(LoggerCleanDirective::class);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_execute_with_dry_run_option(): void
    {
        // Act
        $response = $this->runDirective(LoggerCleanDirective::class, ['--dry-run']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Dry run mode', $response->output);
        $this->assertStringContainsString('no files will be deleted', $response->output);
    }

    public function test_execute_with_verbose_option(): void
    {
        // Act
        $response = $this->runDirective(LoggerCleanDirective::class, ['--verbose']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_execute_with_custom_days(): void
    {
        // Act
        $response = $this->runDirective(LoggerCleanDirective::class, ['--days=60']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_execute_with_dry_run_and_verbose(): void
    {
        // Act
        $response = $this->runDirective(LoggerCleanDirective::class, ['--dry-run', '--verbose']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Dry run mode', $response->output);
        $this->assertStringContainsString('no files will be deleted', $response->output);
    }

    // ==================== TEMPORARY DIRECTIVE TESTS ====================

    public function test_can_create_temporary_directive_for_testing(): void
    {
        // Arrange
        $executed = false;

        // Act
        $this->createTestDirective('test:clean', function ($d) use (&$executed) {
            $executed = true;
            $d->line('Test cleaning executed');
            return ExitCode::SUCCESS;
        });

        $response = $this->runDirective('test:clean');

        // Assert
        $this->assertTrue($executed);
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Test cleaning executed', $response->output);
    }

    // ==================== REGISTRY TESTS ====================

    public function test_register_multiple_directives(): void
    {
        // Arrange
        $directive2 = new LoggerCleanDirective(
            interaction: $this->interaction,
            cleaner: new LogCleanerService(new LogPathService()),
            pathService: new LogPathService(),
            laravelBootstrapper: $this->directiveContainer->make(LaravelBootstrapper::class),
        );

        // Act
        $this->registerDirectives([$this->directive, $directive2]);

        $response1 = $this->runDirective(LoggerCleanDirective::class);
        $response2 = $this->runDirective(LoggerCleanDirective::class);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response1->exitCode);
        $this->assertSame(ExitCode::SUCCESS, $response2->exitCode);
    }

    public function test_clear_registered_directives_removes_directive_from_registry(): void
    {
        // Act: Verify directive is accessible initially
        $responseBefore = $this->runDirective(LoggerCleanDirective::class);
        $this->assertSame(ExitCode::SUCCESS, $responseBefore->exitCode);

        // Act: Clear the registry
        $this->clearRegisteredDirectives();

        // Act: Attempt to run the directive again
        $responseAfter = $this->runDirective(LoggerCleanDirective::class);

        // Assert: Directive is no longer found
        $this->assertNotSame(ExitCode::SUCCESS, $responseAfter->exitCode);
    }

    // ==================== ERROR HANDLING TESTS ====================

    public function test_run_nonexistent_directive_returns_error(): void
    {
        // Act
        $response = $this->runDirective('nonexistent:command');

        // Assert
        $this->assertNotSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertNotEmpty($response->output);
    }
}
