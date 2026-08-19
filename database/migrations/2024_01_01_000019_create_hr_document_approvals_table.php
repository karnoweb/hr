<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'document_approvals', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('hr_document_id')->constrained($prefix.'documents')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained($prefix.'workflow_steps')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_to');
            $table->string('status', 20)->default('pending');
            $table->text('comment')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->dateTime('deadline_at')->nullable();
            $table->timestamps();

            $table->index(['hr_document_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'document_approvals');
    }
};
