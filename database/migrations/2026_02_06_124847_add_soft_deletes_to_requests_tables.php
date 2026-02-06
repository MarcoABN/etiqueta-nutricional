<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->softDeletes(); // Cria coluna 'deleted_at'
        });

        Schema::table('request_items', function (Blueprint $table) {
            $table->softDeletes(); // Cria coluna 'deleted_at'
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('request_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};