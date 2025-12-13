<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->unsignedBigInteger('branch_id')->comment('branch_id');
            $table->string('vin')->nullable()->comment('vin');
            $table->string('plate')->nullable()->comment('plate');
            $table->string('brand')->nullable()->comment('brand');
            $table->string('model')->nullable()->comment('model');
            $table->integer('year')->nullable()->comment('year');
            $table->string('color')->nullable()->comment('color');
            $table->string('status')->default('available')->comment('status');
            $table->decimal('sale_price', 18, 2)->default(0)->comment('sale_price');
            $table->decimal('cost', 18, 2)->default(0)->comment('cost');
            $table->json('extra_attributes')->nullable()->comment('extra_attributes');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->index('branch_id');
        });

        Schema::create('vehicle_contracts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->unsignedBigInteger('vehicle_id')->comment('vehicle_id');
            $table->unsignedBigInteger('customer_id')->comment('customer_id');
            $table->date('start_date')->nullable()->comment('start_date');
            $table->date('end_date')->nullable()->comment('end_date');
            $table->decimal('price', 18, 2)->default(0)->comment('price');
            $table->string('status')->default('active')->comment('status');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
        });

        Schema::create('vehicle_payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->unsignedBigInteger('contract_id')->comment('contract_id');
            $table->string('method')->nullable()->comment('method');
            $table->decimal('amount', 18, 2)->comment('amount');
            $table->timestamp('paid_at')->nullable()->comment('paid_at');
            $table->string('reference')->nullable()->comment('reference');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('contract_id')->references('id')->on('vehicle_contracts')->onDelete('cascade');
        });

        Schema::create('warranties', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->unsignedBigInteger('vehicle_id')->comment('vehicle_id');
            $table->string('provider')->nullable()->comment('provider');
            $table->date('start_date')->nullable()->comment('start_date');
            $table->date('end_date')->nullable()->comment('end_date');
            $table->text('notes')->nullable()->comment('notes');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->unsignedBigInteger('branch_id')->comment('branch_id');
            $table->string('name')->comment('name');
            $table->string('address')->nullable()->comment('address');
            $table->text('notes')->nullable()->comment('notes');
            $table->json('extra_attributes')->nullable()->comment('extra_attributes');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->index('branch_id');
        });

        Schema::create('rental_units', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->unsignedBigInteger('property_id')->comment('property_id');
            $table->string('code')->comment('code');
            $table->string('type')->nullable()->comment('type');
            $table->string('status')->default('available')->comment('status');
            $table->decimal('rent', 18, 2)->default(0)->comment('rent');
            $table->decimal('deposit', 18, 2)->default(0)->comment('deposit');
            $table->json('extra_attributes')->nullable()->comment('extra_attributes');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->unsignedBigInteger('branch_id')->comment('branch_id');
            $table->string('name')->comment('name');
            $table->string('email')->nullable()->comment('email');
            $table->string('phone')->nullable()->comment('phone');
            $table->string('address')->nullable()->comment('address');
            $table->boolean('is_active')->default(true)->comment('is_active');
            $table->json('extra_attributes')->nullable()->comment('extra_attributes');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->index('branch_id');
        });

        Schema::create('rental_contracts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->unsignedBigInteger('branch_id')->comment('branch_id');
            $table->unsignedBigInteger('unit_id')->comment('unit_id');
            $table->unsignedBigInteger('tenant_id')->comment('tenant_id');
            $table->date('start_date')->nullable()->comment('start_date');
            $table->date('end_date')->nullable()->comment('end_date');
            $table->decimal('rent', 18, 2)->default(0)->comment('rent');
            $table->decimal('deposit', 18, 2)->default(0)->comment('deposit');
            $table->string('status')->default('active')->comment('status');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('rental_units')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->index('branch_id');
        });

        Schema::create('rental_invoices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->unsignedBigInteger('contract_id')->comment('contract_id');
            $table->string('code')->nullable()->comment('code');
            $table->string('period')->nullable()->comment('period');
            $table->date('due_date')->nullable()->comment('due_date');
            $table->decimal('amount', 18, 2)->default(0)->comment('amount');
            $table->decimal('paid_total', 18, 2)->default(0)->comment('paid_total');
            $table->string('status')->default('unpaid')->comment('status');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('contract_id')->references('id')->on('rental_contracts')->onDelete('cascade');
        });

        Schema::create('rental_payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->unsignedBigInteger('branch_id')->comment('branch_id');
            $table->unsignedBigInteger('contract_id')->comment('contract_id');
            $table->unsignedBigInteger('invoice_id')->nullable()->comment('invoice_id');
            $table->unsignedBigInteger('created_by')->nullable()->comment('created_by');
            $table->string('method')->nullable()->comment('method');
            $table->decimal('amount', 18, 2)->comment('amount');
            $table->timestamp('paid_at')->nullable()->comment('paid_at');
            $table->string('reference')->nullable()->comment('reference');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('contract_id')->references('id')->on('rental_contracts')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('rental_invoices')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_payments');
        Schema::dropIfExists('rental_invoices');
        Schema::dropIfExists('rental_contracts');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('rental_units');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('vehicle_payments');
        Schema::dropIfExists('vehicle_contracts');
        Schema::dropIfExists('vehicles');
    }
};
