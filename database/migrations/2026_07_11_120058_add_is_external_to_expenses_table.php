<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alterado de 'expenses' para 'settlement_expenses'
        Schema::table('settlement_expenses', function (Blueprint $table) {
            $table->boolean('is_external')->default(false)->after('amount');
        });
    }

    public function down(): void
    {
        // Alterado de 'expenses' para 'settlement_expenses'
        Schema::table('settlement_expenses', function (Blueprint $table) {
            $table->dropColumn('is_external');
        });
    }
};