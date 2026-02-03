<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'leave_requests', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('days', 5, 2)->default(0);
            $table->decimal('hours', 5, 2)->default(0);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->unsignedBigInteger('substitute_employee_id')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'leave_requests');
    }
};
