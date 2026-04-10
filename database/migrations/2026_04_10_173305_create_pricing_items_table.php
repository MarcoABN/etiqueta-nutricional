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
        Schema::create('pricing_items', function (Blueprint $table) {
            $table->id();

            // Mantém foreignId porque a tabela 'pricings' acima foi criada com $table->id() (BigInt)
            $table->foreignId('pricing_id')->constrained()->cascadeOnDelete();

            // CORREÇÃO AQUI: Se settlement_items usa UUID, precisamos referenciar como Uuid
            $table->foreignUuid('settlement_item_id')->constrained()->cascadeOnDelete();

            $table->boolean('is_fractional')->default(false);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('suggested_price', 12, 4)->nullable();
            $table->decimal('profit_margin', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_items');
    }
};
