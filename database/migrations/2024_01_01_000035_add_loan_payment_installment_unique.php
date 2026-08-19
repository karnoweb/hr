<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prevent duplicate installment numbers per loan (HR-079).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'loan_payments', function (Blueprint $table) {
            $table->unique(['loan_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'loan_payments', function (Blueprint $table) {
            $table->dropUnique(['loan_id', 'installment_number']);
        });
    }
};
