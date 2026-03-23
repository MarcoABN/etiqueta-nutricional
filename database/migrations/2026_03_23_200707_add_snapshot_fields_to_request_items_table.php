<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->string('product_name_en')->nullable()->after('product_name');
            $table->string('ncm')->nullable()->after('product_name_en');
            $table->string('barcode')->nullable()->after('ncm');
            $table->decimal('pesoliq', 10, 4)->nullable()->after('barcode');
            $table->decimal('qtunitcx', 10, 4)->nullable()->after('pesoliq');
            $table->string('unidade')->nullable()->after('qtunitcx');
        });
    }

    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->dropColumn(['product_name_en', 'ncm', 'barcode', 'pesoliq', 'qtunitcx', 'unidade']);
        });
    }
};
