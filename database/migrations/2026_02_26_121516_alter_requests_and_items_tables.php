<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->string('shipping_type')->default('Maritimo')->after('status');
        });

        Schema::table('request_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');
            $table->dropColumn('shipping_type'); 
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('shipping_type');
        });

        Schema::table('request_items', function (Blueprint $table) {
            $table->string('shipping_type')->nullable();
            $table->dropColumn('unit_price');
        });
    }
};