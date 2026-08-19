<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix.'sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 191)->unique();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix.'sequences');
    }
};
