<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOfflinePosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public $timeout = 180;

    public function __construct(public array $payload) {}

    public function handle(): void
    {
        // $payload is an array of local POS transactions
        foreach ($this->payload as $tx) {
            DB::transaction(function () use ($tx) {
                // Example upsert to sales and sale_items
                $sale = \App\Models\Sale::query()->updateOrCreate(
                    ['code' => $tx['code']],
                    [
                        'branch_id' => $tx['branch_id'] ?? null,
                        'warehouse_id' => $tx['warehouse_id'] ?? null,
                        'customer_id' => $tx['customer_id'] ?? null,
                        'status' => 'synced',
                        'sub_total' => $tx['sub_total'] ?? 0,
                        'tax_total' => $tx['tax_total'] ?? 0,
                        'discount_total' => $tx['discount_total'] ?? 0,
                        'grand_total' => $tx['grand_total'] ?? 0,
                        'paid_total' => $tx['paid_total'] ?? 0,
                        'notes' => 'Synced from offline POS',
                    ]
                );

                $items = $tx['items'] ?? [];
                foreach ($items as $it) {
                    \App\Models\SaleItem::query()->updateOrCreate(
                        ['sale_id' => $sale->getKey(), 'product_id' => $it['product_id']],
                        [
                            'qty' => $it['qty'] ?? $it['quantity'] ?? 0,
                            'unit_price' => $it['unit_price'] ?? $it['price'] ?? 0,
                            'discount' => $it['discount'] ?? 0,
                            'tax_id' => $it['tax_id'] ?? null,
                            'line_total' => $it['line_total'] ?? $it['total'] ?? 0,
                        ]
                    );
                }
            });
        }

        Log::info('Offline POS sync completed', ['count' => count($this->payload)]);
    }

    public function tags(): array
    {
        return ['pos', 'sync', 'count:'.count($this->payload)];
    }
}
