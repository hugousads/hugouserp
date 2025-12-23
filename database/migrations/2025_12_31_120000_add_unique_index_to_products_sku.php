<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Clean up duplicate SKUs per branch before adding the unique constraint
        $duplicateSkus = DB::table('products')
            ->select('branch_id', 'sku')
            ->whereNotNull('sku')
            ->groupBy('branch_id', 'sku')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicateSkus as $dup) {
            $idsToNull = DB::table('products')
                ->where('branch_id', $dup->branch_id)
                ->where('sku', $dup->sku)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1); // keep the earliest record's SKU within the branch, null out the rest

            if ($idsToNull->isNotEmpty()) {
                DB::table('products')
                    ->whereIn('id', $idsToNull)
                    ->update(['sku' => null]);
            }
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->unique(['branch_id', 'sku'], 'products_branch_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_branch_sku_unique');
        });
    }
};
