<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Update store_orders table to use composite unique index on (external_order_id, branch_id)
     * instead of a single unique index on external_order_id alone.
     * This prevents cross-branch overwrites and enforces branch isolation.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: recreate the table using a temporary table to avoid duplicate definitions
            if (! Schema::hasTable('store_orders')) {
                return;
            }

            Schema::create('store_orders_temp', function (Blueprint $table): void {
                $table->id();
                $table->string('external_order_id', 191);
                $table->string('status', 50)->default('pending')->index();
                $table->unsignedBigInteger('branch_id')->default(0)->index();
                $table->string('currency', 10)->nullable();
                $table->decimal('total', 15, 2)->default(0);
                $table->decimal('discount_total', 15, 2)->default(0);
                $table->decimal('shipping_total', 15, 2)->default(0);
                $table->decimal('tax_total', 15, 2)->default(0);
                $table->json('payload');
                $table->timestamps();

                // Composite unique constraint
                $table->unique(['external_order_id', 'branch_id'], 'store_orders_external_id_branch_unique');
            });

            $orders = DB::table('store_orders')->get();

            if ($orders->isNotEmpty()) {
                DB::table('store_orders_temp')->insert(
                    $orders->map(fn ($order) => [
                        'id' => $order->id,
                        'external_order_id' => $order->external_order_id,
                        'status' => $order->status,
                        'branch_id' => $order->branch_id ?? 0,
                        'currency' => $order->currency,
                        'total' => $order->total,
                        'discount_total' => $order->discount_total,
                        'shipping_total' => $order->shipping_total,
                        'tax_total' => $order->tax_total,
                        'payload' => $order->payload,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ])->all()
                );
            }

            Schema::drop('store_orders');
            Schema::rename('store_orders_temp', 'store_orders');
        } else {
            // Drop the existing unique constraint on external_order_id
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE store_orders DROP INDEX store_orders_external_order_id_unique');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE store_orders DROP CONSTRAINT store_orders_external_order_id_unique');
            }

            Schema::table('store_orders', function (Blueprint $table): void {
                // Make branch_id not nullable since it's now required
                $table->unsignedBigInteger('branch_id')->nullable(false)->change();

                // Add composite unique constraint on (external_order_id, branch_id)
                $table->unique(['external_order_id', 'branch_id'], 'store_orders_external_id_branch_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            if (! Schema::hasTable('store_orders')) {
                return;
            }

            Schema::create('store_orders_legacy', function (Blueprint $table): void {
                $table->id();
                $table->string('external_order_id', 191)->unique();
                $table->string('status', 50)->default('pending')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('currency', 10)->nullable();
                $table->decimal('total', 15, 2)->default(0);
                $table->decimal('discount_total', 15, 2)->default(0);
                $table->decimal('shipping_total', 15, 2)->default(0);
                $table->decimal('tax_total', 15, 2)->default(0);
                $table->json('payload');
                $table->timestamps();
            });

            $orders = DB::table('store_orders')->get();

            if ($orders->isNotEmpty()) {
                DB::table('store_orders_legacy')->insert(
                    $orders->map(fn ($order) => [
                        'id' => $order->id,
                        'external_order_id' => $order->external_order_id,
                        'status' => $order->status,
                        'branch_id' => $order->branch_id ?? null,
                        'currency' => $order->currency,
                        'total' => $order->total,
                        'discount_total' => $order->discount_total,
                        'shipping_total' => $order->shipping_total,
                        'tax_total' => $order->tax_total,
                        'payload' => $order->payload,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ])->all()
                );
            }

            Schema::drop('store_orders');
            Schema::rename('store_orders_legacy', 'store_orders');

            return;
        }

        Schema::table('store_orders', function (Blueprint $table): void {
            // Drop the composite unique constraint
            $table->dropUnique('store_orders_external_id_branch_unique');

            // Make branch_id nullable again
            $table->unsignedBigInteger('branch_id')->nullable()->change();

            // Restore the original unique constraint on external_order_id
            $table->unique('external_order_id');
        });
    }
};
