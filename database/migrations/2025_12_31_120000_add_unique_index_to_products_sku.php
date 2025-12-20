<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Clean up any duplicate SKUs before adding the unique constraint
        $duplicateSkus = DB::table('products')
            ->select('sku')
            ->whereNotNull('sku')
            ->groupBy('sku')
            ->havingRaw('count(*) > 1')
            ->pluck('sku');

        foreach ($duplicateSkus as $sku) {
            $idsToNull = DB::table('products')
                ->where('sku', $sku)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1); // keep the earliest record's SKU, null out the rest

            if ($idsToNull->isNotEmpty()) {
                DB::table('products')
                    ->whereIn('id', $idsToNull)
                    ->update(['sku' => null]);
            }
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->unique('sku', 'products_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_sku_unique');
        });
    }
};
