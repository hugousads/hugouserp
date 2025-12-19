<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use App\Livewire\Components\NotesAttachments;
use App\Models\Attachment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotesAttachmentsTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function test_cross_branch_access_is_forbidden(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $user = User::factory()->create(['branch_id' => $branchA->id]);
        Permission::findOrCreate('customers.view');
        $user->givePermissionTo('customers.view');

        $customerB = Customer::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'CUST-B',
            'name' => 'Branch B Customer',
            'branch_id' => $branchB->id,
        ]);

        Livewire::actingAs($user)
            ->test(NotesAttachments::class, [
                'modelType' => Customer::class,
                'modelId' => $customerB->id,
            ])
            ->assertForbidden();
    }

    public function test_disallowed_mime_types_are_rejected_on_upload(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        Permission::findOrCreate('customers.view');
        $user->givePermissionTo('customers.view');

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'CUST-A',
            'name' => 'Branch A Customer',
            'branch_id' => $branch->id,
        ]);

        Livewire::actingAs($user)
            ->test(NotesAttachments::class, [
                'modelType' => Customer::class,
                'modelId' => $customer->id,
            ])
            ->set('newFiles', [
                UploadedFile::fake()->create('payload.html', 10, 'text/html'),
            ])
            ->call('uploadFiles')
            ->assertHasErrors(['newFiles.0' => 'mimes']);
    }

    public function test_attachment_download_is_authorized_and_private(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        Permission::findOrCreate('customers.view');
        $user->givePermissionTo('customers.view');

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'CUST-A',
            'name' => 'Branch A Customer',
            'branch_id' => $branch->id,
        ]);

        $file = UploadedFile::fake()->create('safe.pdf', 20, 'application/pdf');

        Livewire::actingAs($user)
            ->test(NotesAttachments::class, [
                'modelType' => Customer::class,
                'modelId' => $customer->id,
            ])
            ->set('newFiles', [$file])
            ->call('uploadFiles')
            ->assertHasNoErrors();

        $attachment = Attachment::first();
        $this->assertNotNull($attachment);
        Storage::disk('local')->assertExists($attachment->path);

        $this->assertStringContainsString('/attachments/', $attachment->url);
        $this->assertStringContainsString('/download', $attachment->url);

        $this->actingAs($user)
            ->get($attachment->url)
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="safe.pdf"');
    }
}
