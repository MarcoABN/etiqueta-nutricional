<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_occurrences', function (Blueprint $table) {
            // Adiciona a coluna para múltiplos anexos
            $table->json('attachments')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('demand_occurrences', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};