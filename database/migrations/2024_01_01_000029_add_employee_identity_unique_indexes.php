<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'employees', function (Blueprint $table) {
            $table->dropIndex(['employable_type', 'employable_id']);
            $table->unique(['employable_type', 'employable_id']);
            $table->unique('national_id');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'employees', function (Blueprint $table) {
            $table->dropUnique(['employable_type', 'employable_id']);
            $table->dropUnique(['national_id']);
            $table->index(['employable_type', 'employable_id']);
        });
    }
};
