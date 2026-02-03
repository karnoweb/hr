<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'mission_requests', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('destination');
            $table->text('purpose');
            $table->decimal('days', 5, 2)->default(0);
            $table->string('transportation')->nullable();
            $table->boolean('requires_accommodation')->default(false);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->json('expenses')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'mission_requests');
    }
};
