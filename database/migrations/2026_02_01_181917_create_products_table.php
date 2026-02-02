<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // ID como UUID
            $table->uuid('id')->primary();

            // Identificação
            $table->string('codprod')->nullable()->index(); // Código interno
            $table->string('product_name');
            $table->string('product_name_en')->nullable();

            // Porções
            $table->string('servings_per_container')->nullable();
            $table->string('serving_weight')->nullable();
            $table->string('serving_size_quantity')->nullable();
            $table->string('serving_size_unit')->nullable();

            // Macronutrientes
            $table->string('calories')->nullable();
            $table->string('total_carb')->nullable();
            $table->string('total_carb_dv')->nullable();
            $table->string('total_sugars')->nullable();
            $table->string('added_sugars')->nullable();
            $table->string('added_sugars_dv')->nullable();
            $table->string('sugar_alcohol')->nullable();

            $table->string('protein')->nullable();
            $table->string('protein_dv')->nullable();

            // Gorduras
            $table->string('total_fat')->nullable();
            $table->string('total_fat_dv')->nullable();
            $table->string('sat_fat')->nullable();
            $table->string('sat_fat_dv')->nullable();
            $table->string('trans_fat')->nullable();
            $table->string('trans_fat_dv')->nullable();
            $table->string('poly_fat')->nullable();
            $table->string('mono_fat')->nullable();

            $table->string('fiber')->nullable();
            $table->string('fiber_dv')->nullable();
            $table->string('sodium')->nullable();
            $table->string('sodium_dv')->nullable();
            $table->string('cholesterol')->default('0');
            $table->string('cholesterol_dv')->nullable();

            // Micronutrientes (Lista completa conforme seu Java)
            $table->string('vitamin_d')->nullable();
            $table->string('calcium')->nullable();
            $table->string('iron')->nullable();
            $table->string('potassium')->nullable();
            $table->string('vitamin_a')->nullable();
            $table->string('vitamin_c')->nullable();
            $table->string('vitamin_e')->nullable();
            $table->string('vitamin_k')->nullable();
            $table->string('thiamin')->nullable();
            $table->string('riboflavin')->nullable();
            $table->string('niacin')->nullable();
            $table->string('vitamin_b6')->nullable();
            $table->string('folate')->nullable();
            $table->string('vitamin_b12')->nullable();
            $table->string('biotin')->nullable();
            $table->string('pantothenic_acid')->nullable();
            $table->string('phosphorus')->nullable();
            $table->string('iodine')->nullable();
            $table->string('magnesium')->nullable();
            $table->string('zinc')->nullable();
            $table->string('selenium')->nullable();
            $table->string('copper')->nullable();
            $table->string('manganese')->nullable();
            $table->string('chromium')->nullable();
            $table->string('molybdenum')->nullable();
            $table->string('chloride')->nullable();

            // Composição
            $table->text('ingredients')->nullable();
            $table->text('allergens_contains')->nullable();
            $table->text('allergens_may_contain')->nullable();

            // Fixo (valor default será tratado no Model ou Form)
            $table->text('imported_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};