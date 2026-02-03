<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'payroll_records', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained($prefix . 'payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->unsignedBigInteger('hr_document_id')->nullable();

            $table->unsignedInteger('working_days')->default(0);
            $table->unsignedInteger('present_days')->default(0);
            $table->unsignedInteger('absent_days')->default(0);
            $table->decimal('leave_days_paid', 5, 2)->default(0);
            $table->decimal('leave_days_unpaid', 5, 2)->default(0);
            $table->decimal('mission_days', 5, 2)->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);

            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('overtime_night_minutes')->default(0);
            $table->unsignedInteger('overtime_holiday_minutes')->default(0);

            $table->json('earnings')->nullable();
            $table->json('deductions')->nullable();

            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('gross_earnings', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('gross_salary', 15, 2)->default(0);

            $table->decimal('insurance_employee', 15, 2)->default(0);
            $table->decimal('insurance_employer', 15, 2)->default(0);
            $table->decimal('insurance_unemployment', 15, 2)->default(0);

            $table->decimal('taxable_income', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);

            $table->decimal('net_salary', 15, 2)->default(0);
            $table->decimal('loan_deduction', 15, 2)->default(0);
            $table->decimal('payable', 15, 2)->default(0);

            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->json('calculation_log')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'payroll_records');
    }
};
