<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'salary_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create($prefix . 'salary_structure_items', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained($prefix . 'salary_structures')->cascadeOnDelete();
            $table->foreignId('salary_item_id')->constrained($prefix . 'salary_items')->cascadeOnDelete();
            $table->decimal('value', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['salary_structure_id', 'salary_item_id'], 'structure_item_unique');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'salary_structure_items');
        Schema::dropIfExists($prefix . 'salary_structures');
    }
};
