<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-employee insurance/tax exemption flags (HR-109).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'employees', function (Blueprint $table) {
            $table->boolean('insurance_exempt')->default(false)->after('dependents_count');
            $table->boolean('tax_exempt')->default(false)->after('insurance_exempt');
            $table->decimal('additional_tax_exemption', 15, 2)->nullable()->after('tax_exempt');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'employees', function (Blueprint $table) {
            $table->dropColumn(['insurance_exempt', 'tax_exempt', 'additional_tax_exemption']);
        });
    }
};
