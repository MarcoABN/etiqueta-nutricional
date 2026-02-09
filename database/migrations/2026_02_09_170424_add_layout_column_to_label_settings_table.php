<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Adiciona a coluna se ela ainda não existir
        if (!Schema::hasColumn('label_settings', 'layout')) {
            Schema::table('label_settings', function (Blueprint $table) {
                $table->string('layout')->default('standard')->after('id');
                $table->unique('layout');
            });
        }

        // 2. CORREÇÃO DO POSTGRES: Sincroniza a sequência do ID
        // Isso diz ao banco: "O próximo ID deve ser MAIOR que o maior ID que já existe na tabela".
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('label_settings', 'id'), COALESCE(MAX(id)+1, 1), false) FROM label_settings");
        }

        // 3. Atualiza ou Cria o padrão (Standard)
        // Isso vai pegar o registro ID 1 existente e garantir que ele seja 'standard'
        DB::table('label_settings')->updateOrInsert(
            ['layout' => 'standard'],
            [
                'padding_top' => 2.0, 
                'padding_left' => 2.0, 
                'padding_right' => 2.0, 
                'padding_bottom' => 2.0,
                'gap_width' => 6.0, 
                'font_scale' => 100
            ]
        );

        // 4. Cria o novo padrão (Tabular)
        // Agora que a sequência foi corrigida no passo 2, este insert gerará o ID 2 (ou próximo disponível) sem erro.
        DB::table('label_settings')->updateOrInsert(
            ['layout' => 'tabular'],
            [
                'padding_top' => 1.5, 
                'padding_left' => 1.5, 
                'padding_right' => 1.5, 
                'padding_bottom' => 1.5,
                'gap_width' => 4.0, 
                'font_scale' => 95
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('label_settings', 'layout')) {
            Schema::table('label_settings', function (Blueprint $table) {
                // Removemos o unique e a coluna
                $table->dropUnique(['layout']);
                $table->dropColumn('layout');
            });
        }
        
        // Opcional: Limpar o registro tabular ao reverter
        DB::table('label_settings')->where('layout', 'tabular')->delete();
    }
};