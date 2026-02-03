<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->date('date');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('type', 30)->default('official');
            $table->boolean('is_recurring')->default(false);
            $table->unsignedInteger('recurring_month')->nullable();
            $table->unsignedInteger('recurring_day')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->unique(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'holidays');
    }
};
