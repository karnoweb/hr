<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'workflow_steps', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('workflow_id')->constrained($prefix.'workflows')->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->string('name');
            $table->string('approver_type', 30);
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->json('condition')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('can_reject')->default(true);
            $table->unsignedInteger('timeout_hours')->nullable();
            $table->string('timeout_action', 20)->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'order']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'workflow_steps');
    }
};
