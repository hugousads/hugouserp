<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Shared\DynamicForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DynamicFormSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('private');
    }

    public function test_schema_is_protected_from_client_mutation(): void
    {
        $schema = [
            [
                'name' => 'file',
                'type' => 'file',
                'disk' => 'local',
                'mimes' => ['pdf', 'jpg'],
                'max' => 1024,
            ],
        ];

        $component = Livewire::test(DynamicForm::class, [
            'schema' => $schema,
        ]);

        // Verify that schema is protected with Locked attribute
        $reflection = new \ReflectionClass(DynamicForm::class);
        $schemaProperty = $reflection->getProperty('schema');
        
        // Check if the property has the Locked attribute
        $attributes = $schemaProperty->getAttributes(\Livewire\Attributes\Locked::class);
        $this->assertNotEmpty($attributes, 'Schema property should have Locked attribute');
    }

    public function test_file_upload_respects_server_disk_whitelist(): void
    {
        $schema = [
            [
                'name' => 'document',
                'type' => 'file',
                'disk' => 'public', // Try to set to public (not in whitelist)
                'mimes' => ['pdf'],
                'max' => 1024,
            ],
        ];

        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        Livewire::test(DynamicForm::class, ['schema' => $schema])
            ->set('data.document', $file)
            ->call('submit');

        // File should be stored in 'local' disk (whitelist default), not 'public'
        Storage::disk('local')->assertExists('dynamic-uploads/' . basename($file->hashName()));
        Storage::disk('public')->assertMissing('dynamic-uploads/' . basename($file->hashName()));
    }

    public function test_dangerous_file_extensions_are_blocked(): void
    {
        $schema = [
            [
                'name' => 'file',
                'type' => 'file',
                'disk' => 'local',
                'mimes' => ['php', 'pdf'], // Even if mimes allows it
                'max' => 1024,
            ],
        ];

        $file = UploadedFile::fake()->create('malicious.php', 10);

        Livewire::test(DynamicForm::class, ['schema' => $schema])
            ->set('data.file', $file)
            ->call('submit')
            ->assertHasErrors();
    }

    public function test_html_file_with_script_tags_is_rejected(): void
    {
        $schema = [
            [
                'name' => 'document',
                'type' => 'file',
                'disk' => 'local',
                'mimes' => ['html', 'txt'],
                'max' => 1024,
            ],
        ];

        // Create a malicious HTML file with script tag
        $maliciousContent = '<html><script>alert("XSS")</script></html>';
        $file = UploadedFile::fake()->createWithContent('malicious.html', $maliciousContent);

        Livewire::test(DynamicForm::class, ['schema' => $schema])
            ->set('data.document', $file)
            ->call('submit')
            ->assertHasErrors();
    }

    public function test_svg_file_with_javascript_is_rejected(): void
    {
        $schema = [
            [
                'name' => 'image',
                'type' => 'file',
                'disk' => 'local',
                'mimes' => ['svg', 'png'],
                'max' => 1024,
            ],
        ];

        // Create a malicious SVG file
        $maliciousSvg = '<svg><script>alert("XSS")</script></svg>';
        $file = UploadedFile::fake()->createWithContent('malicious.svg', $maliciousSvg);

        Livewire::test(DynamicForm::class, ['schema' => $schema])
            ->set('data.image', $file)
            ->call('submit')
            ->assertHasErrors();
    }

    public function test_file_exceeding_max_size_is_rejected(): void
    {
        $schema = [
            [
                'name' => 'document',
                'type' => 'file',
                'disk' => 'local',
                'mimes' => ['pdf'],
                'max' => 100, // 100 KB max
            ],
        ];

        // Create a file larger than max
        $file = UploadedFile::fake()->create('large.pdf', 200, 'application/pdf');

        Livewire::test(DynamicForm::class, ['schema' => $schema])
            ->set('data.document', $file)
            ->call('submit')
            ->assertHasErrors();
    }

    public function test_valid_file_upload_succeeds(): void
    {
        $schema = [
            [
                'name' => 'document',
                'type' => 'file',
                'disk' => 'local',
                'mimes' => ['pdf', 'jpg'],
                'max' => 1024,
            ],
        ];

        $file = UploadedFile::fake()->create('valid.pdf', 100, 'application/pdf');

        Livewire::test(DynamicForm::class, ['schema' => $schema])
            ->set('data.document', $file)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDispatched('formSubmitted');

        // Verify file was stored on local disk only
        Storage::disk('local')->assertExists('dynamic-uploads/' . basename($file->hashName()));
    }

    public function test_schema_with_non_whitelisted_disk_defaults_to_local(): void
    {
        $schema = [
            [
                'name' => 'file',
                'type' => 'file',
                'disk' => 's3', // Not in whitelist
                'mimes' => ['pdf'],
                'max' => 1024,
            ],
        ];

        $file = UploadedFile::fake()->create('test.pdf', 50, 'application/pdf');

        Livewire::test(DynamicForm::class, ['schema' => $schema])
            ->set('data.file', $file)
            ->call('submit');

        // Should default to 'local' disk
        Storage::disk('local')->assertExists('dynamic-uploads/' . basename($file->hashName()));
    }
}
