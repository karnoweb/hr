<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'employee_positions', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix.'employees')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained($prefix.'departments')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained($prefix.'positions')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'is_primary']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'employee_positions');
    }
};
