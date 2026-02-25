<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->comment('Responsável pela demanda');
            $table->foreignId('created_by')->constrained('users')->comment('Quem criou a demanda');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('deadline')->nullable();
            $table->text('observation')->nullable();
            $table->enum('status', ['pending', 'started', 'finished'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demands');
    }
};