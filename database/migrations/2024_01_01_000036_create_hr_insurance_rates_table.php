<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned insurance rates (HR-103). Config seeds the first row only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'insurance_rates', function (Blueprint $table) {
            $table->id();
            $table->date('effective_date');
            $table->decimal('employee_rate', 5, 2);
            $table->decimal('employer_rate', 5, 2);
            $table->decimal('unemployment_rate', 5, 2);
            $table->decimal('ceiling_multiplier', 8, 2)->default(7);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('effective_date');
        });

        $rates = config('hr.insurance.social_security');

        DB::table($prefix.'insurance_rates')->insert([
            'effective_date' => '2020-01-01',
            'employee_rate' => $rates['employee_rate'] ?? 7,
            'employer_rate' => $rates['employer_rate'] ?? 20,
            'unemployment_rate' => $rates['unemployment_rate'] ?? 3,
            'ceiling_multiplier' => $rates['ceiling_multiplier'] ?? 7,
            'notes' => 'NEEDS VERIFICATION (legal/regulatory): seeded from config/hr.php — verify rates before production payroll.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'insurance_rates');
    }
};
