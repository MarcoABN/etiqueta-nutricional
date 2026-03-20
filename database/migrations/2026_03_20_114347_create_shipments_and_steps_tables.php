<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ex: Remessa 04
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Ex: Frete Internacional, Alfândega
            $table->string('responsible_name')->nullable(); // Responsável textual
            $table->date('scheduled_date');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_steps');
        Schema::dropIfExists('shipments');
    }
};
