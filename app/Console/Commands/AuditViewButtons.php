<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditViewButtons extends Command
{
    protected $signature = 'audit:buttons {--fix : Attempt to fix issues}';
    protected $description = 'Audit Blade views for buttons without actions or broken wire:click directives';

    protected array $issues = [];

    public function handle(): int
    {
        $this->info('Starting button audit...');
        
        $viewPath = resource_path('views');
        $this->auditDirectory($viewPath);
        
        if (empty($this->issues)) {
            $this->info('✓ No issues found!');
            return Command::SUCCESS;
        }
        
        $this->warn("\nFound " . count($this->issues) . " potential issues:");
        
        foreach ($this->issues as $issue) {
            $this->line("  - {$issue['file']}:{$issue['line']}");
            $this->line("    Issue: {$issue['issue']}");
            $this->line("    Code: " . trim($issue['code']));
            $this->newLine();
        }
        
        if ($this->option('fix')) {
            $this->info('Fix option not yet implemented. Please fix manually.');
        }
        
        return Command::SUCCESS;
    }

    protected function auditDirectory(string $path): void
    {
        $files = File::allFiles($path);
        
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $this->auditFile($file->getPathname());
            }
        }
    }

    protected function auditFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNum => $line) {
            // Check for buttons without actions
            if (preg_match('/<button[^>]*>/', $line)) {
                // Check if button has wire:click, @click, onclick, or type="submit"
                if (!preg_match('/(wire:click|@click|onclick|type=["\']submit["\']|form=)/', $line)) {
                    // Check if it's within a form (rough check)
                    $this->issues[] = [
                        'file' => str_replace(resource_path(), '', $filePath),
                        'line' => $lineNum + 1,
                        'issue' => 'Button without action',
                        'code' => $line,
                    ];
                }
                
                // Check for wire:click without method
                if (preg_match('/wire:click=["\']([^"\']*)["\']/', $line, $matches)) {
                    $method = trim($matches[1]);
                    if (empty($method) || $method === '$refresh') {
                        $this->issues[] = [
                            'file' => str_replace(resource_path(), '', $filePath),
                            'line' => $lineNum + 1,
                            'issue' => 'wire:click with empty or $refresh method',
                            'code' => $line,
                        ];
                    }
                }
            }
            
            // Check for anchor tags that look like buttons but have no href or action
            if (preg_match('/<a[^>]*class=["\'][^"\']*btn[^"\']*["\'][^>]*>/', $line)) {
                if (!preg_match('/(href=|wire:click|@click|onclick)/', $line)) {
                    $this->issues[] = [
                        'file' => str_replace(resource_path(), '', $filePath),
                        'line' => $lineNum + 1,
                        'issue' => 'Button-styled anchor without action',
                        'code' => $line,
                    ];
                }
            }
            
            // Check for disabled buttons that might be permanently disabled
            if (preg_match('/<button[^>]*disabled[^>]*>/', $line)) {
                if (!preg_match('/(wire:loading|x-bind:disabled|\$wire)/', $line)) {
                    $this->issues[] = [
                        'file' => str_replace(resource_path(), '', $filePath),
                        'line' => $lineNum + 1,
                        'issue' => 'Button permanently disabled (not conditionally)',
                        'code' => $line,
                    ];
                }
            }
        }
    }
}
