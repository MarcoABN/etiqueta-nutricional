<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pricings', function (Blueprint $table) {
            // Se o padrão das suas novas tabelas for UUID, mude aqui para $table->uuid('id')->primary();
            $table->id();

            // CORREÇÃO AQUI: foreignUuid em vez de foreignId
            $table->foreignUuid('settlement_id')->constrained()->cascadeOnDelete();

            $table->decimal('ideal_margin', 5, 2)->default(30.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricings');
    }
};
