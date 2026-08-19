<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'loans', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix.'employees')->cascadeOnDelete();
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->string('loan_number', 50)->unique();
            $table->string('type', 30)->default('general');
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('installments');
            $table->decimal('installment_amount', 15, 2);
            $table->decimal('remaining_amount', 15, 2);
            $table->unsignedInteger('remaining_installments');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'loans');
    }
};
