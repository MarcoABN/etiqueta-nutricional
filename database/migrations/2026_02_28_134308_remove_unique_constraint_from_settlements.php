<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Remove a trava de unicidade do banco de dados
            $table->dropUnique('settlements_request_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Restaura caso precise desfazer a migration
            $table->unique('request_id');
        });
    }
};