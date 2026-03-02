<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela principal de Fechamento
        Schema::create('settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->unique()->constrained('requests')->cascadeOnDelete();
            $table->decimal('usd_quote', 10, 2)->nullable();
            $table->decimal('calculation_factor', 10, 3)->default(70.000);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('expense_percentage', 10, 3)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabela de Despesas (1 para N)
        Schema::create('settlement_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('settlement_id')->constrained()->cascadeOnDelete();
            $table->integer('expense_number');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        // Tabela de Itens Calculados (1 para N)
        Schema::create('settlement_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('request_item_id')->constrained('request_items')->cascadeOnDelete();
            $table->decimal('initial_value', 15, 2);
            $table->decimal('partial_value', 15, 2);
            $table->decimal('final_value', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlement_expenses');
        Schema::dropIfExists('settlements');
    }
};