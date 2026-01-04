<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

class BranchSettings extends Component
{
    #[Layout('layouts.app')]
    public ?int $branchId = null;

    /**
     * @var array<int,array{id:int,name:string}>
     */
    public array $branches = [];

    public array $rows = [];

    public function mount(?int $branch = null): void
    {
        // Authorization check - must have settings.branch permission
        $user = Auth::user();
        if (! $user || ! $user->can('settings.branch')) {
            abort(403, __('Unauthorized access to branch settings'));
        }

        $this->branches = Branch::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])
            ->toArray();

        $this->branchId = $branch ?? ($this->branches[0]['id'] ?? null);

        $this->loadRows();
    }

    public function updatedBranchId(): void
    {
        $this->loadRows();
    }

    protected function loadRows(): void
    {
        $this->rows = [];

        if (! $this->branchId) {
            return;
        }

        $prefix = 'branch:'.$this->branchId.':';

        $this->rows = SystemSetting::query()
            ->where('key', 'like', $prefix.'%')
            ->orderBy('key')
            ->get()
            ->map(function (SystemSetting $setting) use ($prefix): array {
                $plainKey = preg_replace('/^'.preg_quote($prefix, '/').'/', '', $setting->key) ?? $setting->key;

                return [
                    'id' => $setting->id,
                    'key' => $plainKey,
                    'value' => is_array($setting->value) ? json_encode($setting->value) : ($setting->value ?? ''),
                ];
            })
            ->toArray();

        if (count($this->rows) === 0) {
            $this->addRow();
        }
    }

    public function addRow(): void
    {
        $this->rows[] = [
            'id' => null,
            'key' => '',
            'value' => '',
        ];
    }

    public function removeRow(int $index): void
    {
        if (isset($this->rows[$index])) {
            unset($this->rows[$index]);
        }
    }

    public function save(): mixed
    {
        if (! $this->branchId) {
            return null;
        }

        $this->validate([
            'branchId' => ['required', 'integer'],
            'rows.*.key' => ['required', 'string', 'max:255'],
        ]);

        $prefix = 'branch:'.$this->branchId.':';

        // نقرأ الإعدادات القديمة للفرع
        $before = SystemSetting::query()
            ->where('group', 'branch')
            ->where('key', 'LIKE', $prefix.'%')
            ->get()
            ->keyBy('key')
            ->map(fn (SystemSetting $s) => [
                'key' => $s->key,
                'value' => $s->value,
            ])
            ->all();

        DB::transaction(function () use ($prefix): void {
            foreach ($this->rows as $row) {
                $plainKey = trim((string) ($row['key'] ?? ''));
                if ($plainKey === '') {
                    continue;
                }

                $value = (string) ($row['value'] ?? '');
                $fullKey = $prefix.$plainKey;

                SystemSetting::query()->updateOrCreate(
                    [
                        'key' => $fullKey,
                        'group' => 'branch',
                    ],
                    [
                        'value' => $value,
                        'type' => 'string',
                        'group' => 'branch',
                    ]
                );
            }
        });

        // الإعدادات بعد التعديل
        $after = SystemSetting::query()
            ->where('group', 'branch')
            ->where('key', 'LIKE', $prefix.'%')
            ->get()
            ->keyBy('key')
            ->map(fn (SystemSetting $s) => [
                'key' => $s->key,
                'value' => $s->value,
            ])
            ->all();

        $changes = [];

        foreach ($after as $key => $row) {
            $beforeRow = $before[$key] ?? null;
            if (! $beforeRow) {
                $changes[] = [
                    'type' => 'created',
                    'key' => $key,
                    'after' => $row,
                ];

                continue;
            }

            if ($beforeRow['value'] !== $row['value']) {
                $changes[] = [
                    'type' => 'updated',
                    'key' => $key,
                    'before' => $beforeRow,
                    'after' => $row,
                ];
            }
        }

        foreach ($before as $key => $row) {
            if (! isset($after[$key])) {
                $changes[] = [
                    'type' => 'deleted',
                    'key' => $key,
                    'before' => $row,
                    'after' => null,
                ];
            }
        }

        if (! empty($changes)) {
            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'target_user_id' => null,
                'action' => 'branch.settings.updated',
                'meta' => [
                    'branch_id' => $this->branchId,
                    'changes' => $changes,
                ],
            ]);
        }

        session()->flash('status', __('Branch settings saved successfully.'));

        $this->loadRows();

        $this->dispatch('settings-saved');

        $this->redirectRoute('admin.settings.branch', ['branch' => $this->branchId], navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.settings.branch-settings');
    }
}
