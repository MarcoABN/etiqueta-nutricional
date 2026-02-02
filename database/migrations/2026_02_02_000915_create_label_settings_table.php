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
        Schema::create('label_settings', function (Blueprint $table) {
            $table->id();
            // Margens externas (para centralizar o bloco todo na etiqueta)
            $table->decimal('padding_top', 4, 1)->default(2.0);    // mm
            $table->decimal('padding_left', 4, 1)->default(2.0);   // mm
            $table->decimal('padding_right', 4, 1)->default(2.0);  // mm
            $table->decimal('padding_bottom', 4, 1)->default(2.0); // mm

            // Vão central (Aquele corte físico)
            $table->decimal('gap_width', 4, 1)->default(6.0);      // mm

            // Ajuste fino de fonte (caso a impressora esteja muito borrada)
            $table->integer('font_scale')->default(100);           // % (Porcentagem)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_settings');
    }
};
