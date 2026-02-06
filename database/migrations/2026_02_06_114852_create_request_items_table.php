<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('request_id')->constrained()->cascadeOnDelete();

            // Relacionamento opcional com produto (pode ser nulo se não cadastrado)
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Dados copiados ou manuais
            $table->integer('winthor_code')->nullable(); // Para ordenação e ref
            $table->string('product_name'); // Obrigatório (vem do produto ou digitado)

            $table->decimal('quantity', 10, 2);
            $table->string('packaging'); // CX, UN, etc
            $table->string('shipping_type'); // Aereo, Maritimo
            $table->text('observation')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
