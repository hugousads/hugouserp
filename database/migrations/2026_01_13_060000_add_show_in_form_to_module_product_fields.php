<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_product_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('module_product_fields', 'show_in_form')) {
                $table->boolean('show_in_form')->default(true)->after('show_in_list');
            }
        });
    }

    public function down(): void
    {
        Schema::table('module_product_fields', function (Blueprint $table) {
            if (Schema::hasColumn('module_product_fields', 'show_in_form')) {
                $table->dropColumn('show_in_form');
            }
        });
    }
};
