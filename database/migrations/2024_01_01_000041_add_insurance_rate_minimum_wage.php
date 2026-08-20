<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version minimum wage with insurance rates (HR-C09).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'insurance_rates', function (Blueprint $table) {
            $table->decimal('minimum_wage', 15, 2)->nullable()->after('ceiling_multiplier');
        });

        $fallback = (float) config('hr.payroll.minimum_wage', 0);

        if ($fallback > 0) {
            DB::table($prefix.'insurance_rates')
                ->whereNull('minimum_wage')
                ->update(['minimum_wage' => $fallback]);
        }
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'insurance_rates', function (Blueprint $table) {
            $table->dropColumn('minimum_wage');
        });
    }
};
