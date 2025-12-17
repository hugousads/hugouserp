<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClosePosDayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public $timeout = 300;

    public function __construct(public ?string $date = null, public ?int $branchId = null) {}

    public function handle(): void
    {
        $date = $this->date ?: now()->toDateString();

        // Example: summarize POS sales of the day and persist closing record
        $sales = \App\Models\Sale::query()
            ->whereDate('created_at', $date)
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->get(['grand_total', 'paid_total']);

        $gross = (float) $sales->sum('grand_total');
        $paid = (float) $sales->sum('paid_total');

        // Save a closing record if you have a model/table for that
        if (class_exists(\App\Models\PosClosing::class)) {
            \App\Models\PosClosing::query()->create([
                'branch_id' => $this->branchId,
                'date' => $date,
                'gross' => $gross,
                'paid' => $paid,
            ]);
        } else {
            Log::info('POS closing summary', compact('date', 'gross', 'paid') + ['branch_id' => $this->branchId]);
        }
    }

    public function tags(): array
    {
        return ['pos', 'closing', 'date:'.($this->date ?? now()->toDateString())];
    }
}
