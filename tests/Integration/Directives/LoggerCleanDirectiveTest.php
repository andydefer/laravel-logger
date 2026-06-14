<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Unit\Directives;

use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelJsonl\Contexts\JsonlContext;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\LaravelJsonl\Strategies\TemporalPathStrategy;
use AndyDefer\Logger\Directives\LoggerCleanDirective;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\LoggerService;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class LoggerCleanDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $directiveService;
    private LoggerService $loggerService;
    private FileSystemService $fileSystem;
    private string $tempDir;
    private HydrationService $hydrationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/logger_clean_test_' . uniqid();

        // Configurer le chemin des logs dans Laravel
        $this->app['config']->set('logger.path', $this->tempDir);
        $this->app['config']->set('logger.retention_days', 30);
        $this->app['config']->set('logger.buffer_size', null);

        // Créer le LoggerService
        $this->fileSystem = new FileSystemService();
        $context = new JsonlContext();
        $this->hydrationService = new HydrationService();
        $strategy = new TemporalPathStrategy($this->tempDir);
        $jsonlService = new JsonlService($strategy, $this->fileSystem, $context);

        $this->loggerService = new LoggerService($jsonlService, $this->hydrationService);
        $this->directiveService = new DirectiveTestingService($this->app);
    }

    protected function tearDown(): void
    {
        $this->directiveService->destroy();

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function createLogRecord(string $date, string $hour, string $type = 'test', array $payload = []): void
    {
        $timestamp = $date . 'T' . $hour . ':00:00Z';
        $dateTime = new DateTimeVO($timestamp);

        $logData = new LogDataRecord(
            type: $type,
            payload: new StrictDataObject($payload ?: ['message' => 'test log', 'value' => 1])
        );

        $record = $this->hydrationService->hydrate(\AndyDefer\Logger\Records\LogRecord::class, [
            'time' => $dateTime,
            'level' => LogLevel::INFO,
            'data' => $logData,
        ]);

        $this->loggerService->log($record);
    }

    private function modifyFileAge(string $date, string $hour, int $ageDays): void
    {
        $filePath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            $date,
            $hour . '.jsonl',
        ]);

        if (file_exists($filePath)) {
            $modificationTime = strtotime("-$ageDays days");
            touch($filePath, $modificationTime);
        }
    }

    /**
     * Crée une instance réelle de la directive avec un vrai contexte
     */
    private function createDirectiveInstance(): LoggerCleanDirective
    {
        $blueprint = new DirectiveBlueprintRecord(
            class: LoggerCleanDirective::class,
            signature: 'logger-clean {--days=30} {--dry-run} {--verbose}',
            description: 'Remove old log files'
        );

        $aliases = new StringTypedCollection();
        $aliases->add('log-clean');
        $aliases->add('clean-logs');

        $context = new DirectiveContext(
            blueprint: $blueprint,
            aliases: $aliases,
            laravelApplication: $this->app,
        );

        $interaction = new DirectiveInteractionService(
            new \AndyDefer\Directive\Dispatchers\RenderDispatcher(),
            new \AndyDefer\Directive\Dispatchers\InputDispatcher(),
        );

        return new LoggerCleanDirective($context, $interaction);
    }

    /**
     * Pour les tests qui ne doivent PAS demander de confirmation (dry-run)
     * On utilise run() de DirectiveTestingService qui gère les arguments
     */
    private function runDirectiveWithArgs(array $arguments = []): \AndyDefer\Directive\Records\DirectiveResponseRecord
    {
        return $this->directiveService->run(LoggerCleanDirective::class, $arguments);
    }

    // ==================== METADATA TESTS ====================

    public function test_get_signature_returns_correct_signature(): void
    {
        $directive = $this->createDirectiveInstance();
        $signature = $directive->getSignature();

        $this->assertStringContainsString('logger-clean', $signature);
        $this->assertStringContainsString('--days=', $signature);
        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('--verbose', $signature);
    }

    public function test_get_description_returns_correct_description(): void
    {
        $directive = $this->createDirectiveInstance();
        $description = $directive->getDescription();

        $this->assertStringContainsString('Remove old log files', $description);
    }

    public function test_get_aliases_returns_correct_aliases(): void
    {
        $directive = $this->createDirectiveInstance();
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('log-clean'));
        $this->assertTrue($aliases->contains('clean-logs'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_should_boot_laravel_returns_true(): void
    {
        $directive = $this->createDirectiveInstance();
        $this->assertTrue($directive->shouldBootLaravel());
    }

    // ==================== TESTS AVEC FICHIERS RÉELS ====================

    public function test_dry_run_mode_with_no_files_to_delete(): void
    {
        // Aucun fichier créé
        $response = $this->runDirectiveWithArgs(['--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Dry run mode', $response->output);
        $this->assertStringContainsString('No files to delete', $response->output);
    }

    public function test_dry_run_mode_with_files_to_delete(): void
    {
        // Créer un fichier ancien
        $this->createLogRecord('2024-01-01', '10', 'old_log', ['value' => 1]);
        $this->modifyFileAge('2024-01-01', '10', 60);

        $oldFile = $this->tempDir . '/2024-01-01/10.jsonl';
        $this->assertFileExists($oldFile);

        $response = $this->runDirectiveWithArgs(['--dry-run', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Dry run mode', $response->output);
        $this->assertStringContainsString('Would delete', $response->output);

        // Le fichier ne doit PAS être supprimé en dry-run
        $this->assertFileExists($oldFile);
    }

    public function test_dry_run_mode_with_verbose_displays_file_details(): void
    {
        $this->createLogRecord('2024-01-01', '10', 'old_log', ['value' => 1]);
        $this->modifyFileAge('2024-01-01', '10', 60);

        $response = $this->runDirectiveWithArgs(['--dry-run', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Files to delete', $response->output);
        $this->assertStringContainsString('10.jsonl', $response->output);
    }

    public function test_verbose_mode_displays_statistics(): void
    {
        $this->createLogRecord(date('Y-m-d'), date('H'), 'recent_log', ['value' => 1]);

        $response = $this->runDirectiveWithArgs(['--verbose', '--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Current statistics:', $response->output);
        $this->assertStringContainsString('Files:', $response->output);
        $this->assertStringContainsString('Size:', $response->output);
        $this->assertStringContainsString('Range:', $response->output);
    }

    public function test_days_option_default_value_is_30(): void
    {
        // Créer un fichier de 31 jours
        $this->createLogRecord('2024-01-01', '10', 'old_log', ['value' => 1]);
        $this->modifyFileAge('2024-01-01', '10', 31);

        $oldFile = $this->tempDir . '/2024-01-01/10.jsonl';
        $this->assertFileExists($oldFile);

        // Par défaut, retention = 30 jours → doit supprimer
        // Note: Ce test demande confirmation, on utilise --dry-run pour éviter la confirmation
        $response = $this->runDirectiveWithArgs(['--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete', $response->output);
    }

    public function test_custom_days_value(): void
    {
        $this->createLogRecord('2024-01-01', '10', 'old_log', ['value' => 1]);
        $this->modifyFileAge('2024-01-01', '10', 15);

        $oldFile = $this->tempDir . '/2024-01-01/10.jsonl';
        $this->assertFileExists($oldFile);

        // --days=10 → fichier de 15 jours doit être supprimé
        $response = $this->runDirectiveWithArgs(['--days=10', '--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete', $response->output);
    }

    public function test_empty_directory_handling(): void
    {
        // Créer un dossier vide (sans fichier)
        $emptyDir = $this->tempDir . '/2024-01-01';
        mkdir($emptyDir, 0755, true);

        $this->assertDirectoryExists($emptyDir);

        $response = $this->runDirectiveWithArgs(['--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // Le dossier vide doit être supprimé lors du nettoyage réel
        $this->assertDirectoryExists($emptyDir); // En dry-run, il reste
    }

    public function test_deletion_shows_no_files_message_when_none_to_delete(): void
    {
        $response = $this->runDirectiveWithArgs(['--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No files to delete', $response->output);
    }

    public function test_handles_missing_log_directory_gracefully(): void
    {
        // Configurer un chemin qui n'existe pas
        $this->app['config']->set('logger.path', '/nonexistent/path/' . uniqid());

        $service = new DirectiveTestingService($this->app);
        $response = $service->run(LoggerCleanDirective::class, ['--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No files to delete', $response->output);

        $service->destroy();
    }

    public function test_handles_invalid_days_value(): void
    {
        // Valeur invalide, doit utiliser la valeur par défaut
        $response = $this->runDirectiveWithArgs(['--days=invalid', '--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    public function test_handles_negative_days_value(): void
    {
        // Valeur négative, doit utiliser la valeur par défaut ou 0
        $response = $this->runDirectiveWithArgs(['--days=-5', '--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    }

    public function test_jsonl_service_is_resolvable(): void
    {
        $jsonlService = $this->app->make(JsonlService::class);
        $this->assertInstanceOf(JsonlService::class, $jsonlService);
    }

    public function test_handles_many_files_efficiently(): void
    {
        // Créer plusieurs fichiers
        for ($i = 0; $i < 10; $i++) {
            $hour = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->createLogRecord('2024-01-01', $hour, "old_log_{$i}", ['value' => $i]);
            $this->modifyFileAge('2024-01-01', $hour, 60);
        }

        $response = $this->runDirectiveWithArgs(['--dry-run', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete 10 file(s)', $response->output);
    }

    public function test_deletion_with_multiple_files_shows_correct_count(): void
    {
        // Créer plusieurs fichiers anciens
        $this->createLogRecord('2024-01-01', '10', 'old_log_1', ['value' => 1]);
        $this->createLogRecord('2024-01-01', '11', 'old_log_2', ['value' => 2]);
        $this->createLogRecord('2024-01-01', '12', 'old_log_3', ['value' => 3]);

        $this->modifyFileAge('2024-01-01', '10', 60);
        $this->modifyFileAge('2024-01-01', '11', 60);
        $this->modifyFileAge('2024-01-01', '12', 60);

        $response = $this->runDirectiveWithArgs(['--dry-run', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete 3 file(s)', $response->output);
    }
}
