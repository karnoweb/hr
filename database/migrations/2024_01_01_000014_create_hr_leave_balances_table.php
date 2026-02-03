<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'leave_balances', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->string('type', 30);
            $table->decimal('entitled_days', 5, 2)->default(0);
            $table->decimal('used_days', 5, 2)->default(0);
            $table->decimal('carried_days', 5, 2)->default(0);
            $table->decimal('adjustment_days', 5, 2)->default(0);
            $table->decimal('remaining_days', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'type']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'leave_balances');
    }
};
