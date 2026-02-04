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
        Schema::table('products', function (Blueprint $table) {
            // pending: aguardando envio
            // processing: enviado para o Ollama, aguardando resposta
            // completed: dados salvos
            // error: falha na leitura
            $table->string('ai_status')->default('pending')->index();
            $table->text('ai_error_message')->nullable(); // Para debugar falhas
            $table->timestamp('last_ai_processed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
