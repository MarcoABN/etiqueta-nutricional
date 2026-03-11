<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_item_expirations', function (Blueprint $table) {
            $table->id();
            // Referência ao item (se você usa UUID no RequestItem, use foreignUuid)
            $table->foreignUuid('request_item_id')->constrained()->cascadeOnDelete();
            $table->date('expiration_date');
            $table->decimal('quantity', 10, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_item_expirations');
    }
};
