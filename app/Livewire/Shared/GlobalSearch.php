<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Project;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public array $results = [];

    public bool $showResults = false;

    public bool $isSearching = false;

    protected static array $columnCache = [];

    protected function scopedQuery($query, User $user)
    {
        $model = $query->getModel();
        $table = $model->getTable();
        $hasBranchColumn = self::$columnCache[$table] ??= Schema::hasColumn($table, 'branch_id');

        return $query->when($user->branch_id && $hasBranchColumn, fn ($q) => $q->where('branch_id', $user->branch_id));
    }

    public function updatedQuery(): void
    {
        $this->search();
    }

    public function search(): void
    {
        $this->results = [];
        $this->showResults = false;
        $this->isSearching = false;

        if (strlen($this->query) < 2) {
            return;
        }

        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->isSearching = true;
        $searchTerm = '%'.$this->query.'%';
        $searchTermLower = '%'.mb_strtolower($this->query, 'UTF-8').'%';

        if ($user->can('inventory.products.view')) {
            $canEdit = $user->can('inventory.products.manage');
            $products = $this->scopedQuery(Product::query(), $user)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($searchTerm, $searchTermLower) {
                    $q->where('name', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(name) LIKE ?', [$searchTermLower])
                        ->orWhere('sku', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$searchTermLower])
                        ->orWhere('barcode', 'like', $searchTerm);
                })
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'name', 'sku']);

            if ($products->isNotEmpty()) {
                $this->results['products'] = [
                    'label' => __('Products'),
                    'icon' => '📦',
                    'route' => 'app.inventory.products.index',
                    'items' => $products->map(fn ($p) => [
                        'id' => $p->id,
                        'title' => $p->name,
                        'subtitle' => 'SKU: '.($p->sku ?: '-'),
                        'route' => $canEdit
                            ? route('app.inventory.products.edit', $p->id)
                            : route('app.inventory.products.index', ['search' => $p->sku]),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('customers.view')) {
            $canEdit = $user->can('customers.manage');
            $customers = $this->scopedQuery(Customer::query(), $user)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($searchTerm, $searchTermLower) {
                    $q->where('name', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(name) LIKE ?', [$searchTermLower])
                        ->orWhere('email', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(email) LIKE ?', [$searchTermLower])
                        ->orWhere('phone', 'like', $searchTerm);
                })
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'name']);

            if ($customers->isNotEmpty()) {
                $this->results['customers'] = [
                    'label' => __('Customers'),
                    'icon' => '👥',
                    'route' => 'customers.index',
                    'items' => $customers->map(fn ($c) => [
                        'id' => $c->id,
                        'title' => $c->name,
                        'subtitle' => __('Customer'),
                        'route' => $canEdit
                            ? route('customers.edit', $c->id)
                            : route('customers.index', ['search' => $c->name]),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('suppliers.view')) {
            $canEdit = $user->can('suppliers.manage');
            $suppliers = $this->scopedQuery(Supplier::query(), $user)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($searchTerm, $searchTermLower) {
                    $q->where('name', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(name) LIKE ?', [$searchTermLower])
                        ->orWhere('email', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(email) LIKE ?', [$searchTermLower])
                        ->orWhere('phone', 'like', $searchTerm);
                })
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'name']);

            if ($suppliers->isNotEmpty()) {
                $this->results['suppliers'] = [
                    'label' => __('Suppliers'),
                    'icon' => '🏭',
                    'route' => 'suppliers.index',
                    'items' => $suppliers->map(fn ($s) => [
                        'id' => $s->id,
                        'title' => $s->name,
                        'subtitle' => __('Supplier'),
                        'route' => $canEdit
                            ? route('suppliers.edit', $s->id)
                            : route('suppliers.index', ['search' => $s->name]),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('sales.view')) {
            $sales = $this->scopedQuery(Sale::query(), $user)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($searchTerm, $searchTermLower) {
                    $q->where('reference_number', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(reference_number) LIKE ?', [$searchTermLower]);
                })
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'status', 'reference_number']);

            if ($sales->isNotEmpty()) {
                $this->results['sales'] = [
                    'label' => __('Sales'),
                    'icon' => '💰',
                    'route' => 'app.sales.index',
                    'items' => $sales->map(fn ($s) => [
                        'id' => $s->id,
                        'title' => $s->reference_number ?: '#'.$s->id,
                        'subtitle' => ucfirst($s->status ?? 'pending'),
                        'route' => route('app.sales.show', $s->id),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('purchases.view')) {
            $canEdit = $user->can('purchases.manage');
            $purchases = $this->scopedQuery(Purchase::query(), $user)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($searchTerm, $searchTermLower) {
                    $q->where('reference_number', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(reference_number) LIKE ?', [$searchTermLower]);
                })
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'reference_number', 'status']);

            if ($purchases->isNotEmpty()) {
                $this->results['purchases'] = [
                    'label' => __('Purchases'),
                    'icon' => '📋',
                    'route' => 'app.purchases.index',
                    'items' => $purchases->map(fn ($p) => [
                        'id' => $p->id,
                        'title' => $p->reference_number ?: '#'.$p->id,
                        'subtitle' => ucfirst($p->status ?? 'pending'),
                        'route' => $canEdit
                            ? route('app.purchases.edit', $p->id)
                            : route('app.purchases.index', ['search' => $p->reference_number]),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('helpdesk.view')) {
            $tickets = $this->scopedQuery(Ticket::query(), $user)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($searchTerm, $searchTermLower) {
                    $q->where('ticket_number', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(ticket_number) LIKE ?', [$searchTermLower])
                        ->orWhere('subject', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(subject) LIKE ?', [$searchTermLower]);
                })
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'ticket_number', 'subject', 'status']);

            if ($tickets->isNotEmpty()) {
                $this->results['tickets'] = [
                    'label' => __('Tickets'),
                    'icon' => '🎫',
                    'route' => 'app.helpdesk.index',
                    'items' => $tickets->map(fn ($t) => [
                        'id' => $t->id,
                        'title' => $t->ticket_number ?: '#'.$t->id,
                        'subtitle' => $t->subject,
                        'route' => route('app.helpdesk.tickets.show', $t->id),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('projects.view')) {
            $projects = $this->scopedQuery(Project::query(), $user)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($searchTerm, $searchTermLower) {
                    $q->where('code', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(code) LIKE ?', [$searchTermLower])
                        ->orWhere('name', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(name) LIKE ?', [$searchTermLower]);
                })
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'code', 'name', 'status']);

            if ($projects->isNotEmpty()) {
                $this->results['projects'] = [
                    'label' => __('Projects'),
                    'icon' => '📂',
                    'route' => 'app.projects.index',
                    'items' => $projects->map(fn ($p) => [
                        'id' => $p->id,
                        'title' => $p->name,
                        'subtitle' => $p->code ?: strtoupper(__('Project')),
                        'route' => route('app.projects.show', $p->id),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('documents.view')) {
            $documents = $this->scopedQuery(Document::query(), $user)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($searchTerm, $searchTermLower) {
                    $q->where('title', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(title) LIKE ?', [$searchTermLower])
                        ->orWhere('code', 'like', $searchTerm)
                        ->orWhereRaw('LOWER(code) LIKE ?', [$searchTermLower]);
                })
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'title', 'code']);

            if ($documents->isNotEmpty()) {
                $this->results['documents'] = [
                    'label' => __('Documents'),
                    'icon' => '📄',
                    'route' => 'app.documents.index',
                    'items' => $documents->map(fn ($d) => [
                        'id' => $d->id,
                        'title' => $d->title ?: ($d->code ?: '#'.$d->id),
                        'subtitle' => $d->code ?: __('Document'),
                        'route' => route('app.documents.show', $d->id),
                    ])->toArray(),
                ];
            }
        }

        $this->showResults = ! empty($this->results);
        $this->isSearching = false;
    }

    public function clearSearch(): void
    {
        $this->query = '';
        $this->results = [];
        $this->showResults = false;
    }

    public function closeResults(): void
    {
        $this->showResults = false;
    }

    public function getTotalResultsProperty(): int
    {
        return collect($this->results)->sum(fn ($group) => count($group['items'] ?? []));
    }

    public function render()
    {
        return view('livewire.shared.global-search');
    }
}
