<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Unit\Directives;

use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Logger\Directives\LoggerCleanDirective;
use AndyDefer\Logger\Tests\IntegrationTestCase;
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
final class LoggerCleanDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Le service provider s'occupe de binder tous les services
        $this->service = new DirectiveTestingService($this->app);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    // ==================== METADATA TESTS ====================

    public function test_get_signature_returns_correct_signature(): void
    {


        $directive = $this->app->make(LoggerCleanDirective::class);
        $signature = $directive->getSignature();

        $this->assertStringContainsString('logger-clean', $signature);
        $this->assertStringContainsString('--days=', $signature);
        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('--verbose', $signature);
    }

    public function test_get_description_returns_correct_description(): void
    {
        $directive = $this->app->make(LoggerCleanDirective::class);
        $description = $directive->getDescription();

        $this->assertStringContainsString('Remove old log files', $description);
    }

    public function test_get_aliases_returns_correct_aliases(): void
    {
        $directive = $this->app->make(LoggerCleanDirective::class);
        $aliases = $directive->getAliases();

        $this->assertInstanceOf(\AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection::class, $aliases);
        $this->assertTrue($aliases->contains('log-clean'));
        $this->assertTrue($aliases->contains('clean-logs'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_should_boot_laravel_returns_true(): void
    {
        $directive = $this->app->make(LoggerCleanDirective::class);
        $this->assertTrue($directive->shouldBootLaravel());
    }

    // ==================== EXECUTION TESTS ====================

    public function test_execute_without_options_cleans_logs_and_returns_success(): void
    {
        $response = $this->service->run(LoggerCleanDirective::class);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_execute_with_dry_run_option(): void
    {
        $response = $this->service->run(LoggerCleanDirective::class, ['--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Dry run mode', $response->output);
        $this->assertStringContainsString('no files will be deleted', $response->output);
    }

    public function test_execute_with_verbose_option(): void
    {
        $response = $this->service->run(LoggerCleanDirective::class, ['--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_execute_with_custom_days(): void
    {
        $response = $this->service->run(LoggerCleanDirective::class, ['--days=60']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_execute_with_dry_run_and_verbose(): void
    {
        $response = $this->service->run(LoggerCleanDirective::class, ['--dry-run', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Dry run mode', $response->output);
        $this->assertStringContainsString('no files will be deleted', $response->output);
    }

    // ==================== TEMPORARY DIRECTIVE TESTS ====================

    public function test_can_create_temporary_directive_for_testing(): void
    {
        $executed = false;

        $this->service->createTestDirective('test:clean', function ($d) use (&$executed) {
            $executed = true;
            $d->line('Test cleaning executed');
            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('test:clean');

        $this->assertTrue($executed);
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Test cleaning executed', $response->output);
    }

    // ==================== REGISTRY TESTS ====================

    public function test_register_multiple_directives(): void
    {
        $response = $this->service->run(LoggerCleanDirective::class);
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_clear_registered_directives_removes_directive_from_registry(): void
    {
        // Enregistrer la directive manuellement
        $directive = $this->app->make(LoggerCleanDirective::class);
        $this->service->registerDirectiveInstance($directive);

        // Vérifier que la directive est accessible
        $responseBefore = $this->service->runDirective('logger-clean');
        $this->assertSame(ExitCode::SUCCESS, $responseBefore->exitCode);

        // Vider le registre
        $this->service->clearRegisteredDirectives();

        // Vérifier que la directive n'est plus trouvée (ne PAS utiliser run() car elle ré-enregistrerait)
        // Utiliser runDirective() qui ne fait que chercher sans ré-enregistrer
        $responseAfter = $this->service->runDirective('logger-clean');
        $this->assertSame(ExitCode::NOT_FOUND, $responseAfter->exitCode);
    }

    // ==================== ERROR HANDLING TESTS ====================

    public function test_run_nonexistent_directive_returns_error(): void
    {
        $response = $this->service->runDirective('nonexistent:command');

        $this->assertNotSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertNotEmpty($response->output);
    }
}
