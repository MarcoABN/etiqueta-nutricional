<?php

// database/migrations/xxxx_xx_xx_create_pctabpr_tn_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PCTABPR_TN', function (Blueprint $table) {
            $table->id(); // ID incremental para facilitar o Filament
            $table->integer('CODFILIAL');
            $table->integer('CODPROD');
            $table->string('CODAUXILIAR')->index(); // Código de Barras
            $table->string('DESCRICAO');
            $table->decimal('CUSTOULTENT', 12, 6)->nullable();
            $table->decimal('PVENDA', 12, 6)->nullable();
            $table->decimal('QTESTOQUE', 12, 6)->nullable();
            $table->decimal('PVENDA_NOVO', 12, 6)->nullable();
            $table->timestamps();

            // Garante que não haja duplicidade de produto na mesma filial
            $table->unique(['CODFILIAL', 'CODPROD']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PCTABPR_TN');
    }
};