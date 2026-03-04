<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_expenses', function (Blueprint $table) {
            $table->boolean('use_custom_quote')->default(false)->after('amount');
            $table->decimal('custom_usd_quote', 10, 4)->nullable()->after('use_custom_quote');
        });
    }

    public function down(): void
    {
        Schema::table('settlement_expenses', function (Blueprint $table) {
            $table->dropColumn(['use_custom_quote', 'custom_usd_quote']);
        });
    }
};