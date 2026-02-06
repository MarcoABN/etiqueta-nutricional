<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Adiciona campos PT-BR logo após os EN
            $table->text('ingredients_pt')->nullable()->after('ingredients');
            $table->text('allergens_contains_pt')->nullable()->after('allergens_contains');
            $table->text('allergens_may_contain_pt')->nullable()->after('allergens_may_contain');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['ingredients_pt', 'allergens_contains_pt', 'allergens_may_contain_pt']);
        });
    }
};