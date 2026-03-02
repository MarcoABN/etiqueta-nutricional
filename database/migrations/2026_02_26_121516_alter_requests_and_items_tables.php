<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verifica se a coluna NÃO existe em 'requests' antes de tentar adicionar
        if (!Schema::hasColumn('requests', 'shipping_type')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->string('shipping_type')->default('Maritimo')->after('status');
            });
        }

        // Aplica as alterações na tabela 'request_items' com validações
        Schema::table('request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('request_items', 'unit_price')) {
                $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');
            }
            
            if (Schema::hasColumn('request_items', 'shipping_type')) {
                $table->dropColumn('shipping_type'); 
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (Schema::hasColumn('requests', 'shipping_type')) {
                $table->dropColumn('shipping_type');
            }
        });

        Schema::table('request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('request_items', 'shipping_type')) {
                $table->string('shipping_type')->nullable();
            }
            
            if (Schema::hasColumn('request_items', 'unit_price')) {
                $table->dropColumn('unit_price');
            }
        });
    }
};