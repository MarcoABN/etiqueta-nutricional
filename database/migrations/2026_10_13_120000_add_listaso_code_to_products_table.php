<?php

use App\Services\ListasoCodeImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('listaso_code', 50)->nullable()->index()->after('codprod');
        });

        // Carga inicial a partir da exportação do Listaso (database/data/listaso_upc.csv).
        // Pode ser reexecutada com: php artisan listaso:import --dry-run
        (new ListasoCodeImporter())->import();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['listaso_code']);
            $table->dropColumn('listaso_code');
        });
    }
};
