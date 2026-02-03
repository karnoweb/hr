<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'departments', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('level')->default(0);
            $table->string('path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')
                ->references('id')
                ->on($prefix . 'departments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'departments');
    }
};
