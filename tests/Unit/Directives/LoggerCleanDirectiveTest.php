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

#[AllowMockObjectsWithoutExpectations]
final class LoggerCleanDirectiveTest extends UnitTestCase
{
    use InteractsWithDirectives;

    private LoggerCleanDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialise l'environnement de test avec Laravel (pour shouldBootLaravel)
        $this->initDirectiveTesting(bootLaravel: true);

        // Créer la directive avec les services réels
        $this->directive = new LoggerCleanDirective(
            interaction: $this->interaction,
            cleaner: new LogCleanerService(new LogPathService()),
            pathService: new LogPathService(),
            laravelBootstrapper: $this->directiveContainer->make(LaravelBootstrapper::class),
        );

        // Enregistrer la directive dans le registry
        $this->registerDirective($this->directive);
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    // ==================== TESTS DIRECTS ====================

    public function test_get_signature_returns_correct_signature(): void
    {
        $signature = $this->directive->getSignature();

        $this->assertStringContainsString('logger-clean', $signature);
        $this->assertStringContainsString('--days=', $signature);
        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('--verbose', $signature);
    }

    public function test_get_description_returns_correct_description(): void
    {
        $description = $this->directive->getDescription();

        $this->assertStringContainsString('Remove old log files', $description);
    }

    public function test_get_aliases_returns_correct_aliases(): void
    {
        $aliases = $this->directive->getAliases();

        $this->assertInstanceOf(\AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection::class, $aliases);
        $this->assertTrue($aliases->contains('log-clean'));
        $this->assertTrue($aliases->contains('clean-logs'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_should_boot_laravel_returns_true(): void
    {
        $this->assertTrue($this->directive->shouldBootLaravel());
    }

    // ==================== TESTS AVEC runDirective() ====================

    public function test_execute_without_options_cleans_logs_and_returns_success(): void
    {
        $response = $this->runDirective(LoggerCleanDirective::class);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_execute_with_dry_run_option(): void
    {
        $response = $this->runDirective(LoggerCleanDirective::class, ['--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Dry run mode', $response->output);
        $this->assertStringContainsString('no files will be deleted', $response->output);
    }

    public function test_execute_with_verbose_option(): void
    {
        $response = $this->runDirective(LoggerCleanDirective::class, ['--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_execute_with_custom_days(): void
    {
        $response = $this->runDirective(LoggerCleanDirective::class, ['--days=60']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }

    public function test_execute_with_dry_run_and_verbose(): void
    {
        $response = $this->runDirective(LoggerCleanDirective::class, ['--dry-run', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Dry run mode', $response->output);
        $this->assertStringContainsString('no files will be deleted', $response->output);
    }

    // ==================== TESTS AVEC createTestDirective() ====================

    public function test_can_create_temporary_directive_for_testing(): void
    {
        $executed = false;

        $this->createTestDirective('test:clean', function ($d) use (&$executed) {
            $executed = true;
            $d->line('Test cleaning executed');
            return ExitCode::SUCCESS;
        });

        $response = $this->runDirective('test:clean');

        $this->assertTrue($executed);
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Test cleaning executed', $response->output);
    }

    // ==================== TESTS DE REGISTRY ====================

    public function test_register_multiple_directives(): void
    {
        $directive2 = new LoggerCleanDirective(
            interaction: $this->interaction,
            cleaner: new LogCleanerService(new LogPathService()),
            pathService: new LogPathService(),
            laravelBootstrapper: $this->directiveContainer->make(LaravelBootstrapper::class),
        );

        // Enregistrer les deux directives
        $this->registerDirectives([$this->directive, $directive2]);

        $response1 = $this->runDirective(LoggerCleanDirective::class);
        $response2 = $this->runDirective(LoggerCleanDirective::class);

        $this->assertSame(ExitCode::SUCCESS, $response1->exitCode);
        $this->assertSame(ExitCode::SUCCESS, $response2->exitCode);
    }

    public function test_clear_registered_directives_removes_directive_from_registry(): void
    {
        // Vérifier que la directive est accessible
        $responseBefore = $this->runDirective(LoggerCleanDirective::class);
        $this->assertSame(ExitCode::SUCCESS, $responseBefore->exitCode);

        // Nettoyer le registry
        $this->clearRegisteredDirectives();

        // La directive n'est plus dans le registry, donc runDirective échoue
        $responseAfter = $this->runDirective(LoggerCleanDirective::class);

        // Ce n'est plus SUCCESS car la directive n'est pas trouvée
        $this->assertNotSame(ExitCode::SUCCESS, $responseAfter->exitCode);
    }

    // ==================== TESTS D'ERREUR ====================

    public function test_run_nonexistent_directive_returns_error(): void
    {
        $response = $this->runDirective('nonexistent:command');

        // La directive n'existe pas
        $this->assertNotSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertNotEmpty($response->output);
    }
}
