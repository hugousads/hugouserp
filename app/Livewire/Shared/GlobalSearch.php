<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Product;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Json;
use Livewire\Component;

/**
 * Global Search Component using Livewire 4 Json Actions
 *
 * This component uses the #[Json] attribute to return search results
 * without triggering a full component re-render. The UI is handled
 * entirely by Alpine.js, making searches fast and smooth.
 */
class GlobalSearch extends Component
{
    protected static array $columnCache = [];

    protected function scopedQuery($query, User $user)
    {
        $model = $query->getModel();
        $table = $model->getTable();
        $hasBranchColumn = self::$columnCache[$table] ??= Schema::hasColumn($table, 'branch_id');

        return $query->when($user->branch_id && $hasBranchColumn, fn ($q) => $q->where('branch_id', $user->branch_id));
    }

    /**
     * Safe route helper to avoid exceptions when route doesn't exist.
     */
    protected function safeRoute(?string $name, mixed $parameters = []): string
    {
        if (! $name || ! Route::has($name)) {
            return '#';
        }

        return route($name, $parameters);
    }

    /**
     * Json action for search - returns JSON without rerendering the component.
     * Results are cached for 10 seconds to avoid hammering the DB.
     */
    #[Json]
    public function search(string $query): array
    {
        if (strlen($query) < 2) {
            return ['results' => [], 'total' => 0];
        }

        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return ['results' => [], 'total' => 0];
        }

        // Short cache to avoid repeated DB queries for same search
        $cacheKey = sprintf('global_search:%d:%s', $user->id, md5($query));

        return Cache::remember($cacheKey, 10, function () use ($query, $user) {
            return $this->performSearch($query, $user);
        });
    }

    /**
     * Perform the actual search across all entities.
     */
    protected function performSearch(string $query, User $user): array
    {
        $results = [];
        $searchTerm = '%'.$query.'%';
        $searchTermLower = '%'.mb_strtolower($query, 'UTF-8').'%';

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
                $results['products'] = [
                    'label' => __('Products'),
                    'icon' => '📦',
                    'route' => $this->safeRoute('app.inventory.products.index'),
                    'items' => $products->map(fn ($p) => [
                        'id' => $p->id,
                        'title' => $p->name,
                        'subtitle' => 'SKU: '.($p->sku ?: '-'),
                        'route' => $canEdit
                            ? $this->safeRoute('app.inventory.products.edit', $p->id)
                            : $this->safeRoute('app.inventory.products.index', ['search' => $p->sku]),
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
                $results['customers'] = [
                    'label' => __('Customers'),
                    'icon' => '👥',
                    'route' => $this->safeRoute('customers.index'),
                    'items' => $customers->map(fn ($c) => [
                        'id' => $c->id,
                        'title' => $c->name,
                        'subtitle' => __('Customer'),
                        'route' => $canEdit
                            ? $this->safeRoute('customers.edit', $c->id)
                            : $this->safeRoute('customers.index', ['search' => $c->name]),
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
                $results['suppliers'] = [
                    'label' => __('Suppliers'),
                    'icon' => '🏭',
                    'route' => $this->safeRoute('suppliers.index'),
                    'items' => $suppliers->map(fn ($s) => [
                        'id' => $s->id,
                        'title' => $s->name,
                        'subtitle' => __('Supplier'),
                        'route' => $canEdit
                            ? $this->safeRoute('suppliers.edit', $s->id)
                            : $this->safeRoute('suppliers.index', ['search' => $s->name]),
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
                $results['sales'] = [
                    'label' => __('Sales'),
                    'icon' => '💰',
                    'route' => $this->safeRoute('app.sales.index'),
                    'items' => $sales->map(fn ($s) => [
                        'id' => $s->id,
                        'title' => $s->reference_number ?: '#'.$s->id,
                        'subtitle' => ucfirst($s->status ?? 'pending'),
                        'route' => $this->safeRoute('app.sales.show', $s->id),
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
                $results['purchases'] = [
                    'label' => __('Purchases'),
                    'icon' => '📋',
                    'route' => $this->safeRoute('app.purchases.index'),
                    'items' => $purchases->map(fn ($p) => [
                        'id' => $p->id,
                        'title' => $p->reference_number ?: '#'.$p->id,
                        'subtitle' => ucfirst($p->status ?? 'pending'),
                        'route' => $canEdit
                            ? $this->safeRoute('app.purchases.edit', $p->id)
                            : $this->safeRoute('app.purchases.index', ['search' => $p->reference_number]),
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
                $results['tickets'] = [
                    'label' => __('Tickets'),
                    'icon' => '🎫',
                    'route' => $this->safeRoute('app.helpdesk.index'),
                    'items' => $tickets->map(fn ($t) => [
                        'id' => $t->id,
                        'title' => $t->ticket_number ?: '#'.$t->id,
                        'subtitle' => $t->subject,
                        'route' => $this->safeRoute('app.helpdesk.tickets.show', $t->id),
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
                $results['projects'] = [
                    'label' => __('Projects'),
                    'icon' => '📂',
                    'route' => $this->safeRoute('app.projects.index'),
                    'items' => $projects->map(fn ($p) => [
                        'id' => $p->id,
                        'title' => $p->name,
                        'subtitle' => $p->code ?: strtoupper(__('Project')),
                        'route' => $this->safeRoute('app.projects.show', $p->id),
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
                $results['documents'] = [
                    'label' => __('Documents'),
                    'icon' => '📄',
                    'route' => $this->safeRoute('app.documents.index'),
                    'items' => $documents->map(fn ($d) => [
                        'id' => $d->id,
                        'title' => $d->title ?: ($d->code ?: '#'.$d->id),
                        'subtitle' => $d->code ?: __('Document'),
                        'route' => $this->safeRoute('app.documents.show', $d->id),
                    ])->toArray(),
                ];
            }
        }

        $total = collect($results)->sum(fn ($group) => count($group['items'] ?? []));

        return ['results' => $results, 'total' => $total];
    }

    public function render()
    {
        return view('livewire.shared.global-search');
    }
}
