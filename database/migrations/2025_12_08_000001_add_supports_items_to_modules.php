<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->boolean('supports_items')->default(false)->after('supports_custom_fields')
                ->comment('Whether this module supports adding/managing items/products');
        });

        // Update existing modules that should support items
        DB::table('modules')
            ->whereIn('key', ['inventory', 'rental', 'pos', 'sales', 'purchases', 'spare_parts'])
            ->orWhere('has_inventory', true)
            ->update(['supports_items' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('supports_items');
        });
    }
};
