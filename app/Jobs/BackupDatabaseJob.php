<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class BackupDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public $timeout = 900; // 15 min

    public function __construct(public bool $verify = true) {}

    public function handle(): void
    {
        // You can replace with spatie/laravel-backup if installed
        $filename = 'backup_'.now()->format('Ymd_His').'.sql.gz';
        $disk = Storage::disk(config('backup.disk', 'local'));
        $path = 'backups/'.$filename;

        // Try using an artisan command if exists; fallback to mysqldump
        if (Artisan::has('db:dump')) {
            Artisan::call('db:dump', ['--path' => $path]);
        } else {
            // Minimal portable fallback using mysqldump with environment variable for password
            // Using MYSQL_PWD env var to avoid password exposure in process list
            // For production, consider using spatie/laravel-backup or .my.cnf config files
            $db = config('database.connections.mysql');

            // Set password via environment variable instead of command line
            putenv('MYSQL_PWD='.($db['password'] ?? ''));

            // All values are from config and properly escaped - no user input
            $cmd = sprintf(
                'mysqldump -h%s -u%s %s | gzip > %s',
                escapeshellarg($db['host'] ?? '127.0.0.1'),
                escapeshellarg($db['username'] ?? ''),
                escapeshellarg($db['database'] ?? ''),
                escapeshellarg(storage_path('app/'.$path))
            );

            @mkdir(dirname(storage_path('app/'.$path)), 0775, true);

            // Execute with proper error handling
            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);

            // Clear the password from environment
            putenv('MYSQL_PWD');

            if ($returnCode !== 0) {
                throw new \RuntimeException('Backup command failed with exit code: '.$returnCode);
            }
        }

        if ($this->verify && ! $disk->exists($path)) {
            throw new \RuntimeException('Backup file was not generated.');
        }
    }

    public function tags(): array
    {
        return ['maintenance', 'backup'];
    }
}
