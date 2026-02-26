<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('ncm')->nullable()->after('curve');
            $table->string('pesoliq')->nullable()->after('ncm');
            $table->string('unidade')->nullable()->after('pesoliq');
            $table->string('qtunitcx')->nullable()->after('unidade');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['ncm', 'pesoliq', 'unidade', 'qtunitcx']);
        });
    }
};