<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'employee_shift_assignments', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix.'employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained($prefix.'shifts')->nullOnDelete();
            $table->foreignId('shift_pattern_id')->nullable()->constrained($prefix.'shift_patterns')->nullOnDelete();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->date('pattern_start_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_active']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'employee_shift_assignments');
    }
};
