<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConsolidateMigrationsCommand extends Command
{
    protected $signature = 'migrations:consolidate 
                            {--backup : Create backup of existing migrations}
                            {--activate : Move consolidated migrations to active directory}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Manage consolidated migrations for MySQL 8.4';

    public function handle(): int
    {
        $migrationsPath = database_path('migrations');
        $consolidatedPath = database_path('migrations_consolidated');
        $backupPath = database_path('migrations_backup_' . date('Y_m_d_His'));

        if (!File::isDirectory($consolidatedPath)) {
            $this->error('Consolidated migrations directory not found.');
            return self::FAILURE;
        }

        $consolidatedCount = count(File::files($consolidatedPath));
        $existingCount = count(File::files($migrationsPath));

        $this->info('Migration Consolidation Summary');
        $this->info('================================');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Existing migrations', $existingCount],
                ['Consolidated migrations', $consolidatedCount],
                ['Reduction', ($existingCount - $consolidatedCount) . ' files'],
            ]
        );

        if ($this->option('backup')) {
            if ($this->option('dry-run')) {
                $this->info("[DRY-RUN] Would backup: {$migrationsPath} → {$backupPath}");
            } else {
                File::copyDirectory($migrationsPath, $backupPath);
                $this->info("✓ Backed up migrations to: {$backupPath}");
            }
        }

        if ($this->option('activate')) {
            if (!$this->option('backup')) {
                $this->warn('⚠ Activating without backup. Use --backup to create a backup first.');
                if (!$this->confirm('Continue without backup?')) {
                    return self::FAILURE;
                }
            }

            if ($this->option('dry-run')) {
                $this->info('[DRY-RUN] Would move consolidated migrations to: ' . $migrationsPath);
                return self::SUCCESS;
            }

            // Clear existing migrations
            File::cleanDirectory($migrationsPath);

            // Copy consolidated migrations
            foreach (File::files($consolidatedPath) as $file) {
                File::copy($file->getPathname(), $migrationsPath . '/' . $file->getFilename());
            }

            $this->info('✓ Consolidated migrations activated.');
            $this->info('');
            $this->warn('⚠ Important: Run "php artisan migrate:fresh" on a fresh database.');
            $this->warn('  For existing databases, you need data migration scripts.');
        }

        $this->info('');
        $this->info('MySQL 8.4 Features Enabled:');
        $this->line('  • utf8mb4_0900_ai_ci collation (optimized sorting)');
        $this->line('  • Full-text search indexes');
        $this->line('  • JSON columns for flexible data');
        $this->line('  • Composite indexes for common queries');
        $this->line('  • Proper foreign key cascades');

        return self::SUCCESS;
    }
}
