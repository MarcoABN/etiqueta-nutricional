<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->comment('Quem registrou a ocorrência');
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_occurrences');
    }
};