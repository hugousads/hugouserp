<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\ModuleRegistrationService;
use App\Services\RoleTemplateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSystemDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'docs:generate
                            {type? : Type of documentation (modules, roles, api, all)}
                            {--format=markdown : Output format (markdown, html, json)}
                            {--output= : Output directory}';

    /**
     * The console command description.
     */
    protected $description = 'Generate comprehensive system documentation';

    protected string $outputDir;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type') ?? 'all';
        $this->outputDir = $this->option('output') ?: base_path('docs/generated');

        // Create output directory
        if (! File::isDirectory($this->outputDir)) {
            File::makeDirectory($this->outputDir, 0755, true);
        }

        $this->info('═══════════════════════════════════════');
        $this->info('   System Documentation Generator');
        $this->info('═══════════════════════════════════════');
        $this->line('');

        match ($type) {
            'modules' => $this->generateModulesDoc(),
            'roles' => $this->generateRolesDoc(),
            'api' => $this->generateApiDoc(),
            'all' => $this->generateAllDocs(),
            default => $this->error('Invalid type. Use: modules, roles, api, or all'),
        };

        $this->line('');
        $this->info('✓ Documentation generated in: '.$this->outputDir);

        return self::SUCCESS;
    }

    /**
     * Generate all documentation
     */
    protected function generateAllDocs(): void
    {
        $this->generateModulesDoc();
        $this->generateRolesDoc();
        $this->generateApiDoc();
        $this->generateIndexDoc();
    }

    /**
     * Generate modules documentation
     */
    protected function generateModulesDoc(): void
    {
        $this->info('Generating modules documentation...');

        $service = app(ModuleRegistrationService::class);
        $modules = $service->getAllModulesWithNavigation();

        $content = $this->generateModulesMarkdown($modules);

        File::put($this->outputDir.'/modules.md', $content);
        $this->line('  ✓ modules.md');
    }

    /**
     * Generate modules markdown
     */
    protected function generateModulesMarkdown(array $modules): string
    {
        $md = "# System Modules Documentation\n\n";
        $md .= "**Generated:** ".now()->format('Y-m-d H:i:s')."\n\n";
        $md .= "## Overview\n\n";
        $md .= "This document provides comprehensive information about all modules in the HugousERP system.\n\n";
        $md .= "**Total Modules:** ".count($modules)."\n\n";
        $md .= "---\n\n";

        // Group by category
        $grouped = collect($modules)->groupBy('category');

        foreach ($grouped as $category => $categoryModules) {
            $md .= "## ".ucfirst($category)." Modules\n\n";

            foreach ($categoryModules as $module) {
                $md .= "### {$module['icon']} {$module['name']}\n\n";
                $md .= "**Key:** `{$module['key']}`  \n";
                $md .= "**Status:** ".($module['is_active'] ? '✅ Active' : '❌ Inactive')."\n\n";

                if (! empty($module['description'])) {
                    $md .= "**Description:** {$module['description']}\n\n";
                }

                // Navigation
                if (! empty($module['navigation'])) {
                    $md .= "#### Navigation\n\n";
                    foreach ($module['navigation'] as $nav) {
                        $md .= $this->formatNavigationItem($nav);
                    }
                    $md .= "\n";
                }

                $md .= "---\n\n";
            }
        }

        return $md;
    }

    /**
     * Format navigation item recursively
     */
    protected function formatNavigationItem(array $nav, int $level = 0): string
    {
        $indent = str_repeat('  ', $level);
        $md = "{$indent}- **{$nav['icon']} {$nav['label']}**";

        if (! empty($nav['route'])) {
            $md .= " (`{$nav['route']}`)";
        }

        if (! empty($nav['permissions'])) {
            $md .= " - *Permissions: ".implode(', ', $nav['permissions']).'*';
        }

        $md .= "\n";

        if (! empty($nav['children'])) {
            foreach ($nav['children'] as $child) {
                $md .= $this->formatNavigationItem($child, $level + 1);
            }
        }

        return $md;
    }

    /**
     * Generate roles documentation
     */
    protected function generateRolesDoc(): void
    {
        $this->info('Generating roles documentation...');

        $service = app(RoleTemplateService::class);
        $templates = $service->getTemplates();

        $content = $this->generateRolesMarkdown($templates);

        File::put($this->outputDir.'/roles.md', $content);
        $this->line('  ✓ roles.md');
    }

    /**
     * Generate roles markdown
     */
    protected function generateRolesMarkdown(array $templates): string
    {
        $md = "# Role Templates Documentation\n\n";
        $md .= "**Generated:** ".now()->format('Y-m-d H:i:s')."\n\n";
        $md .= "## Overview\n\n";
        $md .= "This document describes all available role templates in the system.\n\n";
        $md .= "**Total Templates:** ".count($templates)."\n\n";
        $md .= "---\n\n";

        foreach ($templates as $key => $template) {
            $md .= "## {$template['name']}\n\n";
            $md .= "**Key:** `{$key}`  \n";
            $md .= "**Arabic Name:** {$template['name_ar']}  \n";
            $md .= "**Description:** {$template['description']}\n\n";

            $md .= "### Permissions\n\n";
            if (in_array('*', $template['permissions'])) {
                $md .= "- **All Permissions** (Full System Access)\n\n";
            } else {
                $permGroups = $this->groupPermissions($template['permissions']);
                foreach ($permGroups as $group => $perms) {
                    $md .= "#### ".ucfirst($group)."\n\n";
                    foreach ($perms as $perm) {
                        $md .= "- `{$perm}`\n";
                    }
                    $md .= "\n";
                }
            }

            $md .= "### Usage\n\n";
            $md .= "```bash\n";
            $md .= "php artisan role:manage create {$key}\n";
            $md .= "```\n\n";
            $md .= "---\n\n";
        }

        return $md;
    }

    /**
     * Group permissions by module
     */
    protected function groupPermissions(array $permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            if (str_contains($permission, '.')) {
                [$module] = explode('.', $permission, 2);
                $grouped[$module][] = $permission;
            } else {
                $grouped['other'][] = $permission;
            }
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Generate API documentation
     */
    protected function generateApiDoc(): void
    {
        $this->info('Generating API documentation...');

        $content = "# API Documentation\n\n";
        $content .= "**Generated:** ".now()->format('Y-m-d H:i:s')."\n\n";
        $content .= "## Overview\n\n";
        $content .= "This document describes the HugousERP API endpoints.\n\n";
        $content .= "## Authentication\n\n";
        $content .= "All API requests require authentication using Laravel Sanctum tokens.\n\n";
        $content .= "```bash\n";
        $content .= "curl -H \"Authorization: Bearer YOUR_TOKEN\" https://api.example.com/endpoint\n";
        $content .= "```\n\n";

        File::put($this->outputDir.'/api.md', $content);
        $this->line('  ✓ api.md');
    }

    /**
     * Generate index documentation
     */
    protected function generateIndexDoc(): void
    {
        $this->info('Generating index documentation...');

        $md = "# HugousERP System Documentation\n\n";
        $md .= "**Generated:** ".now()->format('Y-m-d H:i:s')."\n\n";
        $md .= "## Table of Contents\n\n";
        $md .= "1. [Modules Documentation](modules.md)\n";
        $md .= "2. [Role Templates Documentation](roles.md)\n";
        $md .= "3. [API Documentation](api.md)\n\n";
        $md .= "## System Overview\n\n";
        $md .= "HugousERP is a comprehensive Enterprise Resource Planning system built with Laravel.\n\n";
        $md .= "### Key Features\n\n";
        $md .= "- Multi-branch management\n";
        $md .= "- Role-based access control\n";
        $md .= "- Modular architecture\n";
        $md .= "- Real-time updates\n";
        $md .= "- Comprehensive reporting\n";
        $md .= "- Multi-language support (Arabic/English)\n\n";
        $md .= "### Quick Links\n\n";
        $md .= "- [Installation Guide](../README.md)\n";
        $md .= "- [Architecture Documentation](../ARCHITECTURE.md)\n";
        $md .= "- [Security Guidelines](../SECURITY.md)\n";
        $md .= "- [Contributing Guide](../CONTRIBUTING.md)\n\n";
        $md .= "## Documentation Commands\n\n";
        $md .= "```bash\n";
        $md .= "# Generate all documentation\n";
        $md .= "php artisan docs:generate all\n\n";
        $md .= "# Generate specific documentation\n";
        $md .= "php artisan docs:generate modules\n";
        $md .= "php artisan docs:generate roles\n";
        $md .= "php artisan docs:generate api\n";
        $md .= "```\n\n";

        File::put($this->outputDir.'/README.md', $md);
        $this->line('  ✓ README.md (index)');
    }
}
