<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'documents', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('employee_id')->constrained($prefix.'employees')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('document_number', 50)->unique();
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('data')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'type']);
            $table->index('status');
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'documents');
    }
};
