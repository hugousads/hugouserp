<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Store;

use App\Models\Branch;
use App\Models\Store;
use App\Models\StoreIntegration;
use App\Models\StoreSyncLog;
use App\Services\Store\StoreSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Stores extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $search = '';

    public ?string $typeFilter = null;

    public ?string $statusFilter = null;

    public bool $showModal = false;

    public bool $showSyncModal = false;

    public ?int $editingId = null;

    public ?int $syncingStoreId = null;

    public string $name = '';

    public string $type = 'shopify';

    public string $url = '';

    public ?int $branch_id = null;

    public bool $is_active = true;

    public array $settings = [];

    public string $api_key = '';

    public string $api_secret = '';

    public string $access_token = '';

    public string $webhook_secret = '';

    public array $sync_settings = [
        'sync_products' => true,
        'sync_inventory' => true,
        'sync_orders' => true,
        'sync_customers' => false,
        'auto_sync' => false,
        'sync_interval' => 60,
        'sync_modules' => [],
        'sync_categories' => [],
    ];

    public array $branches = [];

    public array $syncLogs = [];

    protected array $storeTypes = [
        'shopify' => 'Shopify',
        'woocommerce' => 'WooCommerce',
        'laravel' => 'Laravel API',
        'custom' => 'Custom API',
    ];

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:shopify,woocommerce,laravel,custom',
            'url' => 'required|url|max:500',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean',
            'api_key' => 'nullable|string|max:500',
            'api_secret' => 'nullable|string|max:500',
            'access_token' => 'nullable|string|max:1000',
            'webhook_secret' => 'nullable|string|max:255',
        ];
    }

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->can('stores.view')) {
            abort(403);
        }

        // Select name_ar only if column exists (defensive for older schemas)
        $columns = ['id', 'name'];
        if (Schema::hasColumn('branches', 'name_ar')) {
            $columns[] = 'name_ar';
        }
        
        $this->branches = Branch::where('is_active', true)
            ->orderBy('name')
            ->get($columns)
            ->toArray();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openModal(?int $id = null): void
    {
        $this->resetForm();

        if ($id) {
            $store = Store::with('integration')->findOrFail($id);
            $this->editingId = $store->id;
            $this->name = $store->name;
            $this->type = $store->type;
            $this->url = $store->url;
            $this->branch_id = $store->branch_id;
            $this->is_active = $store->is_active;
            $this->settings = $store->settings ?? [];
            $this->sync_settings = array_merge($this->sync_settings, $store->settings['sync'] ?? []);

            if ($store->integration) {
                $this->api_key = $store->integration->api_key ?? '';
                $this->api_secret = $store->integration->api_secret ?? '';
                $this->access_token = $store->integration->access_token ?? '';
                $this->webhook_secret = $store->integration->webhook_secret ?? '';
            }
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->type = 'shopify';
        $this->url = '';
        $this->branch_id = null;
        $this->is_active = true;
        $this->settings = [];
        $this->api_key = '';
        $this->api_secret = '';
        $this->access_token = '';
        $this->webhook_secret = '';
        $this->sync_settings = [
            'sync_products' => true,
            'sync_inventory' => true,
            'sync_orders' => true,
            'sync_customers' => false,
            'auto_sync' => false,
            'sync_interval' => 60,
            'sync_modules' => [],
            'sync_categories' => [],
        ];
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        DB::beginTransaction();

        try {
            $storeData = [
                'name' => $this->name,
                'type' => $this->type,
                'url' => rtrim($this->url, '/'),
                'branch_id' => $this->branch_id,
                'is_active' => $this->is_active,
                'settings' => array_merge($this->settings, ['sync' => $this->sync_settings]),
            ];

            if ($this->editingId) {
                $store = Store::findOrFail($this->editingId);
                $store->update($storeData);
            } else {
                $store = Store::create($storeData);
            }

            $integrationData = [
                'platform' => $this->type,
                'is_active' => $this->is_active,
            ];

            if ($this->api_key) {
                $integrationData['api_key'] = $this->api_key;
            }
            if ($this->api_secret) {
                $integrationData['api_secret'] = $this->api_secret;
            }
            if ($this->access_token) {
                $integrationData['access_token'] = $this->access_token;
            }
            if ($this->webhook_secret) {
                $integrationData['webhook_secret'] = $this->webhook_secret;
            }

            StoreIntegration::updateOrCreate(
                ['store_id' => $store->id],
                $integrationData
            );

            DB::commit();

            $this->closeModal();
            session()->flash('success', $this->editingId ? __('Store updated successfully') : __('Store created successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', __('Error saving store: ').$e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $store = Store::findOrFail($id);
        $store->delete();

        session()->flash('success', __('Store deleted successfully'));
    }

    public function toggleStatus(int $id): void
    {
        $store = Store::findOrFail($id);
        $store->update(['is_active' => ! $store->is_active]);

        if ($store->integration) {
            $store->integration->update(['is_active' => $store->is_active]);
        }
    }

    public function openSyncModal(int $storeId): void
    {
        $this->syncingStoreId = $storeId;
        $this->loadSyncLogs();
        $this->showSyncModal = true;
    }

    public function closeSyncModal(): void
    {
        $this->showSyncModal = false;
        $this->syncingStoreId = null;
        $this->syncLogs = [];
    }

    protected function loadSyncLogs(): void
    {
        if ($this->syncingStoreId) {
            $this->syncLogs = StoreSyncLog::where('store_id', $this->syncingStoreId)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->toArray();
        }
    }

    public function syncProducts(): void
    {
        if (! $this->syncingStoreId) {
            return;
        }

        $store = Store::findOrFail($this->syncingStoreId);
        $service = new StoreSyncService;

        try {
            if ($store->isShopify()) {
                $log = $service->pullProductsFromShopify($store);
            } elseif ($store->isWooCommerce()) {
                $log = $service->pullProductsFromWooCommerce($store);
            } else {
                session()->flash('error', __('Sync not supported for this store type'));

                return;
            }

            $this->loadSyncLogs();
            session()->flash('success', __('Products synced: ').$log->success_count.' / '.($log->success_count + $log->failed_count));

        } catch (\Exception $e) {
            session()->flash('error', __('Sync failed: ').$e->getMessage());
        }
    }

    public function syncInventory(): void
    {
        if (! $this->syncingStoreId) {
            return;
        }

        $store = Store::findOrFail($this->syncingStoreId);
        $service = new StoreSyncService;

        try {
            if ($store->isShopify()) {
                $log = $service->pushStockToShopify($store);
            } elseif ($store->isWooCommerce()) {
                $log = $service->pushStockToWooCommerce($store);
            } else {
                session()->flash('error', __('Sync not supported for this store type'));

                return;
            }

            $this->loadSyncLogs();
            session()->flash('success', __('Inventory synced: ').$log->success_count.' / '.($log->success_count + $log->failed_count));

        } catch (\Exception $e) {
            session()->flash('error', __('Sync failed: ').$e->getMessage());
        }
    }

    public function syncOrders(): void
    {
        if (! $this->syncingStoreId) {
            return;
        }

        $store = Store::findOrFail($this->syncingStoreId);
        $service = new StoreSyncService;

        try {
            if ($store->isShopify()) {
                $log = $service->pullOrdersFromShopify($store);
            } elseif ($store->isWooCommerce()) {
                $log = $service->pullOrdersFromWooCommerce($store);
            } else {
                session()->flash('error', __('Sync not supported for this store type'));

                return;
            }

            $this->loadSyncLogs();
            session()->flash('success', __('Orders synced: ').$log->success_count.' / '.($log->success_count + $log->failed_count));

        } catch (\Exception $e) {
            session()->flash('error', __('Sync failed: ').$e->getMessage());
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $query = Store::with(['branch', 'integration', 'syncLogs' => fn ($q) => $q->latest()->limit(1)]);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('url', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        if ($this->statusFilter !== null) {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        $stores = $query->orderByDesc('created_at')->paginate(15);

        $modules = \App\Models\Module::where('is_active', true)
            ->where('has_inventory', true)
            ->orderBy('name')
            ->get(['id', 'name', 'name_ar']);

        return view('livewire.admin.store.stores', [
            'stores' => $stores,
            'storeTypes' => $this->storeTypes,
            'modules' => $modules,
        ]);
    }
}
