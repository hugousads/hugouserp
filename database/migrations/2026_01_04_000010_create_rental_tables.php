<?php

declare(strict_types=1);

/**
 * Consolidated Rental & Property Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - Rental units, contracts
 * - Tenants, payments
 * - Vehicle rentals
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function setTableOptions(Blueprint $table): void
    {
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_0900_ai_ci';
    }

    public function up(): void
    {
        // Rental periods configuration
        Schema::create('rental_periods', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('module_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('unit', 50); // hour, day, week, month, year
            $table->integer('duration');
            $table->decimal('price_multiplier', 8, 4)->default(1);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tenants (renters)
        Schema::create('tenants', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('type', 50)->default('individual');
            
            // Contact
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            
            // Identity
            $table->string('national_id', 50)->nullable();
            $table->string('passport_number', 50)->nullable();
            $table->string('commercial_register', 100)->nullable();
            $table->string('tax_number', 100)->nullable();
            
            // Address
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            
            // Emergency contact
            $table->string('emergency_contact', 255)->nullable();
            $table->string('emergency_phone', 50)->nullable();
            
            // Financial
            $table->decimal('balance', 18, 4)->default(0);
            $table->decimal('deposit_balance', 18, 4)->default(0);
            
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();
            
            $table->text('notes')->nullable();
            $table->json('documents')->nullable();
            $table->json('custom_fields')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'is_active']);
        });

        // Properties (rental units - buildings, apartments, etc.)
        Schema::create('properties', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('type', 50); // building, apartment, office, store, land
            
            // Location
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Details
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->integer('floors')->nullable();
            $table->string('year_built', 4)->nullable();
            
            // Pricing
            $table->decimal('purchase_price', 18, 4)->nullable();
            $table->decimal('market_value', 18, 4)->nullable();
            
            $table->string('status', 50)->default('available'); // available, occupied, maintenance
            $table->boolean('is_active')->default(true)->index();
            
            $table->json('amenities')->nullable();
            $table->json('images')->nullable();
            $table->text('description')->nullable();
            
            $table->foreignId('manager_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // Rental units (sub-units of properties)
        Schema::create('rental_units', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()
                ->constrained('properties')
                ->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('type', 50); // apartment, office, store, room
            $table->string('floor', 50)->nullable();
            
            // Size
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            
            // Pricing
            $table->decimal('daily_rate', 18, 4)->nullable();
            $table->decimal('weekly_rate', 18, 4)->nullable();
            $table->decimal('monthly_rate', 18, 4)->nullable();
            $table->decimal('yearly_rate', 18, 4)->nullable();
            $table->decimal('deposit_amount', 18, 4)->nullable();
            
            // Utilities
            $table->boolean('utilities_included')->default(false);
            $table->decimal('electricity_meter', 18, 2)->nullable();
            $table->decimal('water_meter', 18, 2)->nullable();
            
            $table->string('status', 50)->default('available'); // available, occupied, reserved, maintenance
            $table->boolean('is_active')->default(true)->index();
            
            $table->json('amenities')->nullable();
            $table->json('images')->nullable();
            $table->text('description')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['property_id', 'status']);
        });

        // Rental contracts
        Schema::create('rental_contracts', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('rental_units');
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('contract_number', 100)->unique();
            $table->string('type', 50)->default('lease'); // lease, short_term
            $table->string('status', 50)->default('draft'); // draft, active, expired, terminated
            
            // Dates
            $table->date('start_date');
            $table->date('end_date');
            $table->date('actual_end_date')->nullable();
            $table->timestamp('expiration_notified_at')->nullable();
            
            // Financial
            $table->decimal('rent_amount', 18, 4);
            $table->string('rent_frequency', 50)->default('monthly');
            $table->decimal('deposit_amount', 18, 4)->default(0);
            $table->decimal('deposit_paid', 18, 4)->default(0);
            $table->integer('payment_day')->nullable();
            $table->decimal('late_fee_amount', 18, 4)->default(0);
            $table->decimal('late_fee_percent', 5, 2)->default(0);
            $table->integer('grace_period_days')->default(0);
            
            // Utilities
            $table->boolean('utilities_included')->default(false);
            $table->decimal('electricity_opening', 18, 2)->nullable();
            $table->decimal('water_opening', 18, 2)->nullable();
            
            // Terms
            $table->text('terms_conditions')->nullable();
            $table->text('special_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->json('documents')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        // Rental invoices
        Schema::create('rental_invoices', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('contract_id')
                ->constrained('rental_contracts')
                ->cascadeOnDelete();
            $table->string('invoice_number', 100)->unique();
            $table->string('type', 50)->default('rent'); // rent, deposit, utilities, penalty
            $table->string('status', 50)->default('pending'); // pending, paid, partial, cancelled
            $table->date('invoice_date');
            $table->date('due_date');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            
            $table->decimal('rent_amount', 18, 4)->default(0);
            $table->decimal('utilities_amount', 18, 4)->default(0);
            $table->decimal('late_fee', 18, 4)->default(0);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->decimal('paid_amount', 18, 4)->default(0);
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['contract_id', 'status']);
            $table->index(['due_date', 'status']);
        });

        // Rental payments
        Schema::create('rental_payments', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('contract_id')
                ->constrained('rental_contracts')
                ->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()
                ->constrained('rental_invoices')
                ->nullOnDelete();
            $table->string('receipt_number', 100)->unique();
            $table->date('payment_date');
            $table->decimal('amount', 18, 4);
            $table->string('payment_method', 50);
            $table->string('type', 50)->default('rent'); // rent, deposit, refund
            $table->string('status', 50)->default('completed');
            
            $table->string('cheque_number', 100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('bank_name', 255)->nullable();
            
            $table->text('notes')->nullable();
            
            $table->foreignId('received_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            
            $table->index(['contract_id', 'payment_date']);
        });

        // Vehicle models
        Schema::create('vehicle_models', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->string('type', 50); // car, suv, truck, van, motorcycle
            $table->string('year', 4)->nullable();
            $table->integer('seats')->nullable();
            $table->string('transmission', 50)->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->decimal('daily_rate', 18, 4)->nullable();
            $table->string('image', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Vehicles
        Schema::create('vehicles', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('model_id')->nullable()
                ->constrained('vehicle_models')
                ->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('plate_number', 50)->unique();
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->string('color', 50)->nullable();
            $table->string('year', 4)->nullable();
            $table->string('vin', 50)->nullable();
            $table->string('engine_number', 100)->nullable();
            
            // Current state
            $table->string('status', 50)->default('available'); // available, rented, maintenance, sold
            $table->decimal('current_mileage', 12, 2)->default(0);
            $table->decimal('fuel_level_percent', 5, 2)->nullable();
            
            // Pricing
            $table->decimal('hourly_rate', 18, 4)->nullable();
            $table->decimal('daily_rate', 18, 4)->nullable();
            $table->decimal('weekly_rate', 18, 4)->nullable();
            $table->decimal('monthly_rate', 18, 4)->nullable();
            $table->decimal('deposit_amount', 18, 4)->nullable();
            $table->decimal('excess_km_rate', 18, 4)->nullable();
            
            // Purchase info
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 18, 4)->nullable();
            $table->date('registration_date')->nullable();
            $table->date('registration_expiry')->nullable();
            
            // Insurance
            $table->string('insurance_company', 255)->nullable();
            $table->string('insurance_policy', 100)->nullable();
            $table->date('insurance_expiry')->nullable();
            
            // Maintenance
            $table->date('last_service_date')->nullable();
            $table->decimal('last_service_mileage', 12, 2)->nullable();
            $table->date('next_service_date')->nullable();
            $table->decimal('next_service_mileage', 12, 2)->nullable();
            
            $table->boolean('is_active')->default(true)->index();
            
            $table->json('features')->nullable();
            $table->json('images')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'status']);
        });

        // Vehicle contracts (rentals)
        Schema::create('vehicle_contracts', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('contract_number', 100)->unique();
            $table->string('status', 50)->default('draft'); // draft, active, completed, cancelled
            
            // Dates
            $table->timestamp('pickup_datetime');
            $table->timestamp('return_datetime');
            $table->timestamp('actual_pickup')->nullable();
            $table->timestamp('actual_return')->nullable();
            
            // Mileage
            $table->decimal('pickup_mileage', 12, 2)->nullable();
            $table->decimal('return_mileage', 12, 2)->nullable();
            $table->decimal('included_km', 12, 2)->nullable();
            
            // Fuel
            $table->decimal('pickup_fuel_level', 5, 2)->nullable();
            $table->decimal('return_fuel_level', 5, 2)->nullable();
            
            // Location
            $table->string('pickup_location', 255)->nullable();
            $table->string('return_location', 255)->nullable();
            
            // Financial
            $table->decimal('daily_rate', 18, 4);
            $table->decimal('total_days', 8, 2);
            $table->decimal('rental_amount', 18, 4);
            $table->decimal('deposit_amount', 18, 4)->default(0);
            $table->decimal('deposit_paid', 18, 4)->default(0);
            $table->decimal('extra_charges', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->decimal('paid_amount', 18, 4)->default(0);
            
            // Driver
            $table->string('driver_name', 255)->nullable();
            $table->string('driver_license', 100)->nullable();
            $table->date('license_expiry')->nullable();
            
            $table->json('inspection_checklist')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'status']);
            $table->index(['vehicle_id', 'status']);
            $table->index(['pickup_datetime', 'return_datetime']);
        });

        // Vehicle payments
        Schema::create('vehicle_payments', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('contract_id')
                ->constrained('vehicle_contracts')
                ->cascadeOnDelete();
            $table->string('receipt_number', 100)->unique();
            $table->date('payment_date');
            $table->decimal('amount', 18, 4);
            $table->string('payment_method', 50);
            $table->string('type', 50)->default('rental'); // rental, deposit, refund, extra
            $table->string('status', 50)->default('completed');
            $table->text('notes')->nullable();
            
            $table->foreignId('received_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_payments');
        Schema::dropIfExists('vehicle_contracts');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('rental_payments');
        Schema::dropIfExists('rental_invoices');
        Schema::dropIfExists('rental_contracts');
        Schema::dropIfExists('rental_units');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('rental_periods');
    }
};
