<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'loan_payments', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('loan_id')->constrained($prefix . 'loans')->cascadeOnDelete();
            $table->unsignedBigInteger('payroll_record_id')->nullable();
            $table->unsignedInteger('installment_number');
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'loan_payments');
    }
};
