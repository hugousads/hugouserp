<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class TranslationCompletenessTest extends TestCase
{
    /**
     * Minimum required translation coverage percentage.
     */
    private const MIN_COVERAGE_PERCENT = 85.0;

    /**
     * Test that all translation keys used in the app exist in both English and Arabic.
     */
    public function test_all_translation_keys_exist_in_both_languages(): void
    {
        $enJson = json_decode(file_get_contents(lang_path('en.json')), true);
        $arJson = json_decode(file_get_contents(lang_path('ar.json')), true);

        $this->assertIsArray($enJson, 'English JSON translations should be valid');
        $this->assertIsArray($arJson, 'Arabic JSON translations should be valid');

        $enKeys = array_keys($enJson);
        $arKeys = array_keys($arJson);

        $missingInArabic = array_diff($enKeys, $arKeys);
        $missingInEnglish = array_diff($arKeys, $enKeys);

        $this->assertEmpty(
            $missingInArabic,
            'Missing translations in Arabic: ' . implode(', ', array_slice($missingInArabic, 0, 10))
        );

        $this->assertEmpty(
            $missingInEnglish,
            'Missing translations in English: ' . implode(', ', array_slice($missingInEnglish, 0, 10))
        );
    }

    /**
     * Test that Arabic translations are not empty or same as English.
     */
    public function test_arabic_translations_are_properly_translated(): void
    {
        $enJson = json_decode(file_get_contents(lang_path('en.json')), true);
        $arJson = json_decode(file_get_contents(lang_path('ar.json')), true);

        $untranslated = [];

        foreach ($enJson as $key => $enValue) {
            if (isset($arJson[$key])) {
                // Check if Arabic translation is empty or exactly same as English
                if (empty($arJson[$key]) || $arJson[$key] === $enValue) {
                    // Allow some technical terms to be the same
                    if (!$this->isTechnicalTerm($key)) {
                        $untranslated[] = $key;
                    }
                }
            }
        }

        // Calculate coverage
        $totalKeys = count($enJson);
        $untranslatedCount = count($untranslated);
        $coverage = ($totalKeys - $untranslatedCount) / $totalKeys * 100;

        // Require minimum coverage (allowing for technical strings and code snippets)
        $this->assertGreaterThanOrEqual(
            self::MIN_COVERAGE_PERCENT,
            $coverage,
            sprintf(
                'Arabic translation coverage is %.1f%% (below %.1f%%). Untranslated: %s',
                $coverage,
                self::MIN_COVERAGE_PERCENT,
                implode(', ', array_slice($untranslated, 0, 10))
            )
        );
    }

    /**
     * Test that sidebar labels are all translatable.
     */
    public function test_sidebar_labels_are_translatable(): void
    {
        $sidebarFile = resource_path('views/components/sidebar/main.blade.php');
        $this->assertFileExists($sidebarFile);

        $content = file_get_contents($sidebarFile);

        // Check that all label attributes use translation
        preg_match_all('/label="([^"]+)"/', $content, $matches);

        $enJson = json_decode(file_get_contents(lang_path('en.json')), true);
        $arJson = json_decode(file_get_contents(lang_path('ar.json')), true);

        $missingLabels = [];

        foreach ($matches[1] as $label) {
            if (!isset($enJson[$label]) || !isset($arJson[$label])) {
                $missingLabels[] = $label;
            }
        }

        $this->assertEmpty(
            $missingLabels,
            'Sidebar labels missing translations: ' . implode(', ', $missingLabels)
        );
    }

    /**
     * Test that section headers in sidebar are translatable.
     */
    public function test_sidebar_section_headers_are_translatable(): void
    {
        $sidebarFile = resource_path('views/components/sidebar/main.blade.php');
        $this->assertFileExists($sidebarFile);

        $content = file_get_contents($sidebarFile);

        // Extract section headers
        preg_match_all("/__\('([^']+)'\)/", $content, $matches);

        $enJson = json_decode(file_get_contents(lang_path('en.json')), true);
        $arJson = json_decode(file_get_contents(lang_path('ar.json')), true);

        $missingHeaders = [];

        foreach ($matches[1] as $header) {
            if (!isset($enJson[$header]) || !isset($arJson[$header])) {
                $missingHeaders[] = $header;
            }
        }

        $this->assertEmpty(
            $missingHeaders,
            'Section headers missing translations: ' . implode(', ', $missingHeaders)
        );
    }

    /**
     * Test that common UI strings exist in translations.
     */
    public function test_common_ui_strings_exist(): void
    {
        $commonStrings = [
            'Save', 'Cancel', 'Delete', 'Edit', 'Create', 'Search',
            'Actions', 'Status', 'Active', 'Inactive', 'Dashboard',
            'Settings', 'Reports', 'Users', 'Yes', 'No'
        ];

        $enJson = json_decode(file_get_contents(lang_path('en.json')), true);
        $arJson = json_decode(file_get_contents(lang_path('ar.json')), true);

        $missing = [];

        foreach ($commonStrings as $string) {
            if (!isset($enJson[$string]) || !isset($arJson[$string])) {
                $missing[] = $string;
            }
        }

        $this->assertEmpty(
            $missing,
            'Common UI strings missing: ' . implode(', ', $missing)
        );
    }

    /**
     * Smoke test: Check that Arabic UI doesn't contain common English UI tokens.
     * This helps prevent regressions where English strings leak into Arabic locale.
     */
    public function test_arabic_locale_smoke_test(): void
    {
        $arJson = json_decode(file_get_contents(lang_path('ar.json')), true);
        
        // Common English UI tokens that should NOT appear in Arabic translations
        // (except as part of technical terms or placeholders)
        $englishTokens = [
            'WORKSPACE', 'SALES & PURCHASES', 'Business Modules',
        ];
        
        $violations = [];
        
        foreach ($englishTokens as $token) {
            // Check if this exact English token exists as a value in Arabic translations
            // where it shouldn't (i.e., the Arabic value equals the English token)
            if (isset($arJson[$token]) && $arJson[$token] === $token) {
                $violations[] = $token;
            }
        }
        
        $this->assertEmpty(
            $violations,
            'Arabic translations contain untranslated English UI tokens: ' . implode(', ', $violations)
        );
    }

    /**
     * Smoke test: Check that English UI doesn't contain Arabic text.
     * This helps ensure proper locale separation.
     */
    public function test_english_locale_smoke_test(): void
    {
        $enJson = json_decode(file_get_contents(lang_path('en.json')), true);
        
        $arabicPattern = '/[\x{0600}-\x{06FF}]/u'; // Arabic Unicode range
        $violations = [];
        
        foreach ($enJson as $key => $value) {
            if (is_string($value) && preg_match($arabicPattern, $value)) {
                $violations[] = $key;
            }
        }
        
        $this->assertEmpty(
            $violations,
            'English translations contain Arabic text: ' . implode(', ', array_slice($violations, 0, 10))
        );
    }

    /**
     * Check if a key represents a technical term that can be untranslated.
     */
    private function isTechnicalTerm(string $key): bool
    {
        // Extended list of technical terms that are acceptable to remain in English
        $technicalTerms = [
            // Technical acronyms
            'ERP', 'API', 'SMS', 'POS', 'SKU', 'N/A', 'OK', 'URL', 'HTTP', 'HTTPS',
            'CSS', 'HTML', 'JSON', 'XML', 'PDF', 'CSV', 'ID', 'UUID', 'URI', 'FTP',
            'VAPID', 'reCAPTCHA', 'ISO', 'VIP', 'GRN', 'FEFO', 'BOM', 'HRM', 'TTL',
            'VAT', 'SLA', 'CRUD', 'JWT', '2FA', 'QR',
            // Brand names and product names that should stay in English
            'Laravel', 'Sanctum', 'Shopify', 'WooCommerce', 'Amazon', 'S3',
            'Firebase', 'Turbo', 'WordPress', 'Livewire', 'Alpine',
            // Technical prefixes/patterns
            'validation.', 'permission.', 'permission_group.', 'role.', 'notifications.',
            'e.g.', 'i.e.',
            // Vehicle types (commonly kept in English)
            'SUV', 'Sedan',
            // Technical patterns (code snippets, examples, placeholders)
            ':', '{', '}', '(', ')', '[', ']', '->', '=>', '//', '/*',
            'example.com', '@example', 'email@', 'http://', 'https://',
            // Technical configuration strings
            'Cron', '* * *', 'env', 'config', 'cache',
            // Common technical phrases
            'Found :count', 'Showing :from', 'For example',
            // File extensions and paths
            '.php', '.json', '.csv', '.xlsx', '.pdf', '.doc',
        ];

        foreach ($technicalTerms as $term) {
            if (str_contains($key, $term)) {
                return true;
            }
        }

        // Also allow very short strings (1-2 chars) and pure numbers
        if (strlen($key) <= 2 || is_numeric($key)) {
            return true;
        }

        // Allow strings that look like code examples or technical references
        // Note: Hyphen at end of character class to avoid range interpretation
        if (preg_match('/^[A-Z0-9_.\/-]+$/', $key)) {
            return true;
        }

        // Allow strings that contain email-like patterns
        if (str_contains($key, '@') && str_contains($key, '.')) {
            return true;
        }

        return false;
    }
}
