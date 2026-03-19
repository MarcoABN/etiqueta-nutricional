<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained()->cascadeOnDelete();
            
            $table->integer('pallet_number');
            $table->integer('total_pallets');
            $table->decimal('gross_weight', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->text('importer_text')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallets');
    }
};