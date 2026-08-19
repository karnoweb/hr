<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'overtime_records', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix.'employees')->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained($prefix.'attendance_records')->nullOnDelete();
            $table->date('date');
            $table->unsignedInteger('calculated_minutes')->default(0);
            $table->unsignedInteger('approved_minutes')->nullable();
            $table->string('type', 20)->default('regular');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'overtime_records');
    }
};
