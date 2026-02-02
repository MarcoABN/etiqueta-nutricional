<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <--- Importante adicionar isso

return new class extends Migration
{
    public function up(): void
    {
        // 1. Adiciona o campo barcode
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode')->nullable()->unique()->after('codprod');
        });

        // 2. Converte codprod para Inteiro usando SQL Raw (Correção do Erro)
        // O comando 'USING codprod::integer' diz ao Postgres como transformar texto em número
        DB::statement('ALTER TABLE products ALTER COLUMN codprod TYPE integer USING (codprod::integer)');

        // 3. Aplica a restrição de Unicidade
        Schema::table('products', function (Blueprint $table) {
            $table->unique('codprod');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Remove Unique e Barcode
            $table->dropUnique(['codprod']);
            $table->dropColumn('barcode');
        });

        // Reverte codprod para String
        DB::statement('ALTER TABLE products ALTER COLUMN codprod TYPE varchar(255)');
    }
};