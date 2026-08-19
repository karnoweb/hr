<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'employee_salaries', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix.'employees')->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->nullable()->constrained($prefix.'salary_structures')->nullOnDelete();
            $table->decimal('base_salary', 15, 2);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_current']);
            $table->index('effective_date');
        });

        Schema::create($prefix.'employee_salary_items', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('employee_salary_id')->constrained($prefix.'employee_salaries')->cascadeOnDelete();
            $table->foreignId('salary_item_id')->constrained($prefix.'salary_items')->cascadeOnDelete();
            $table->decimal('value', 15, 2);
            $table->timestamps();

            $table->unique(['employee_salary_id', 'salary_item_id'], 'employee_salary_item_unique');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'employee_salary_items');
        Schema::dropIfExists($prefix.'employee_salaries');
    }
};
