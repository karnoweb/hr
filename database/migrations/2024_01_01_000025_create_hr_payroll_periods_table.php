<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedInteger('year');
            $table->unsignedInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('working_days');
            $table->string('status', 20)->default('draft');
            $table->dateTime('calculated_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'year', 'month']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'payroll_periods');
    }
};
