<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Altera para 10 dígitos no total, sendo 4 após a vírgula
            $table->decimal('usd_quote', 10, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Reverte para 2 casas decimais caso faça um rollback
            $table->decimal('usd_quote', 10, 2)->change();
        });
    }
};
