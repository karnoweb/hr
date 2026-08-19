<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned tax brackets (HR-105). Config seeds the first row only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('fiscal_year');
            $table->date('effective_date');
            $table->decimal('annual_exemption', 15, 2);
            $table->json('brackets');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['effective_date', 'fiscal_year']);
        });

        $tax = config('hr.tax');

        DB::table($prefix.'tax_brackets')->insert([
            'fiscal_year' => 1403,
            'effective_date' => '2020-01-01',
            'annual_exemption' => $tax['annual_exemption'] ?? 672000000,
            'brackets' => json_encode($tax['brackets'] ?? []),
            'notes' => 'NEEDS VERIFICATION (legal/regulatory): seeded from config/hr.php fiscal year 1403 assumption — verify brackets before production payroll.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'tax_brackets');
    }
};
