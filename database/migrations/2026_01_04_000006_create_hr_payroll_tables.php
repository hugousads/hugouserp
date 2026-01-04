<?php

declare(strict_types=1);

/**
 * Consolidated HR & Payroll Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - Employees, attendance, leaves
 * - Payroll processing
 * - Shifts management
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
        // Employees
        Schema::create('hr_employees', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('branch_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            
            // Personal info
            $table->string('employee_code', 50)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('first_name_ar', 100)->nullable();
            $table->string('last_name_ar', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('marital_status', 50)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('passport_number', 50)->nullable();
            $table->date('passport_expiry')->nullable();
            
            // Address
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            
            // Emergency contact
            $table->string('emergency_contact_name', 255)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('emergency_contact_relation', 100)->nullable();
            
            // Employment info
            $table->string('position', 255)->nullable();
            $table->string('department', 255)->nullable();
            $table->foreignId('manager_id')->nullable()
                ->constrained('hr_employees')
                ->nullOnDelete();
            $table->date('hire_date');
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('employment_type', 50)->default('full_time'); // full_time, part_time, contract, intern
            $table->string('status', 50)->default('active'); // active, on_leave, suspended, terminated
            
            // Salary info
            $table->decimal('basic_salary', 18, 4)->default(0);
            $table->string('salary_currency', 3)->default('EGP');
            $table->string('payment_method', 50)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_account', 100)->nullable();
            $table->string('bank_iban', 50)->nullable();
            
            // Allowances
            $table->decimal('housing_allowance', 18, 4)->default(0);
            $table->decimal('transport_allowance', 18, 4)->default(0);
            $table->decimal('meal_allowance', 18, 4)->default(0);
            $table->decimal('other_allowances', 18, 4)->default(0);
            
            // Leave balances
            $table->integer('annual_leave_balance')->default(0);
            $table->integer('sick_leave_balance')->default(0);
            
            // Working hours
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->json('work_days')->nullable();
            
            // Additional
            $table->string('profile_photo', 500)->nullable();
            $table->json('documents')->nullable();
            $table->json('skills')->nullable();
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'status']);
            $table->index(['department', 'status']);
            $table->fullText(['first_name', 'last_name', 'email']);
        });

        // Branch-Employee pivot for multi-branch employees
        Schema::create('branch_employee', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->constrained('hr_employees')
                ->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            $table->unique(['branch_id', 'employee_id']);
        });

        // Shifts
        Schema::create('shifts', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->integer('break_duration_minutes')->default(0);
            $table->integer('late_grace_minutes')->default(15);
            $table->integer('early_leave_grace_minutes')->default(15);
            $table->decimal('overtime_rate', 5, 2)->default(1.5);
            $table->json('working_days')->nullable();
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Employee shifts assignment
        Schema::create('employee_shifts', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('hr_employees')
                ->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            
            $table->index(['employee_id', 'is_current']);
        });

        // Attendance
        Schema::create('attendances', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('hr_employees')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date')->index();
            
            // Clock times
            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->time('scheduled_in')->nullable();
            $table->time('scheduled_out')->nullable();
            
            // Status calculations
            $table->string('status', 50)->default('present'); // present, absent, late, half_day, on_leave
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->integer('worked_minutes')->default(0);
            
            // Location tracking
            $table->string('clock_in_ip', 45)->nullable();
            $table->string('clock_out_ip', 45)->nullable();
            $table->decimal('clock_in_latitude', 10, 8)->nullable();
            $table->decimal('clock_in_longitude', 11, 8)->nullable();
            $table->decimal('clock_out_latitude', 10, 8)->nullable();
            $table->decimal('clock_out_longitude', 11, 8)->nullable();
            
            $table->text('notes')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            
            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });

        // Leave requests
        Schema::create('leave_requests', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('hr_employees')
                ->cascadeOnDelete();
            $table->string('leave_type', 50); // annual, sick, unpaid, maternity, etc.
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_count', 5, 2);
            $table->string('status', 50)->default('pending'); // pending, approved, rejected, cancelled
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('attachment', 500)->nullable();
            
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        // Payrolls
        Schema::create('payrolls', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')
                ->constrained('hr_employees')
                ->cascadeOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->integer('year');
            $table->integer('month');
            $table->string('status', 50)->default('draft'); // draft, calculated, approved, paid
            
            // Earnings
            $table->decimal('basic_salary', 18, 4)->default(0);
            $table->decimal('housing_allowance', 18, 4)->default(0);
            $table->decimal('transport_allowance', 18, 4)->default(0);
            $table->decimal('meal_allowance', 18, 4)->default(0);
            $table->decimal('other_allowances', 18, 4)->default(0);
            $table->decimal('overtime_amount', 18, 4)->default(0);
            $table->decimal('bonus', 18, 4)->default(0);
            $table->decimal('commission', 18, 4)->default(0);
            $table->decimal('gross_salary', 18, 4)->default(0);
            
            // Deductions
            $table->decimal('tax_deduction', 18, 4)->default(0);
            $table->decimal('insurance_deduction', 18, 4)->default(0);
            $table->decimal('loan_deduction', 18, 4)->default(0);
            $table->decimal('advance_deduction', 18, 4)->default(0);
            $table->decimal('absence_deduction', 18, 4)->default(0);
            $table->decimal('late_deduction', 18, 4)->default(0);
            $table->decimal('other_deductions', 18, 4)->default(0);
            $table->decimal('total_deductions', 18, 4)->default(0);
            
            // Net
            $table->decimal('net_salary', 18, 4)->default(0);
            
            // Working summary
            $table->integer('working_days')->default(0);
            $table->integer('present_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('late_days')->default(0);
            $table->integer('overtime_hours')->default(0);
            $table->integer('leave_days')->default(0);
            
            // Payment
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('bank_reference', 100)->nullable();
            
            $table->text('notes')->nullable();
            $table->json('breakdown')->nullable();
            
            $table->foreignId('calculated_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['employee_id', 'year', 'month']);
            $table->index(['year', 'month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('branch_employee');
        Schema::dropIfExists('hr_employees');
    }
};
