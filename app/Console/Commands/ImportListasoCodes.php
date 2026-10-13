<?php

namespace App\Console\Commands;

use App\Services\ListasoCodeImporter;
use Illuminate\Console\Command;

class ImportListasoCodes extends Command
{
    protected $signature = 'listaso:import
        {file? : CSV (item_code;upc;item_name). Padrão: database/data/listaso_upc.csv}
        {--force : Sobrescreve códigos Listaso já preenchidos}
        {--dry-run : Apenas mostra o resultado, sem gravar}';

    protected $description = 'Vincula o Cód. Listaso aos produtos cruzando pelo código de barras (UPC)';

    public function handle(): int
    {
        $importer = new ListasoCodeImporter($this->argument('file'));
        $report = $importer->import((bool) $this->option('force'), (bool) $this->option('dry-run'));

        if ($report['matched']) {
            $this->info('Vinculados: ' . count($report['matched']));
            $this->table(
                ['Cód. WinThor', 'Cód. Barras', 'Produto', 'Cód. Listaso', 'Critério'],
                array_map(fn($r) => [$r['codprod'], $r['barcode'], $r['product_name'], $r['listaso_code'], $r['method']], $report['matched'])
            );
        }

        if ($report['ambiguous']) {
            $this->warn('Ambíguos (mesmo código de barras com mais de um item no Listaso) - mantidos vazios: ' . count($report['ambiguous']));
            $this->table(
                ['Cód. WinThor', 'Cód. Barras', 'Produto', 'Candidatos Listaso'],
                array_map(fn($r) => [$r['codprod'], $r['barcode'], $r['product_name'], $r['candidates']], $report['ambiguous'])
            );
        }

        $this->line('Produtos sem correspondência no Listaso: ' . count($report['not_found']));

        if ($report['unused']->isNotEmpty()) {
            $this->warn('Itens do Listaso sem produto correspondente no NX: ' . $report['unused']->count());
            $this->table(
                ['Item Code', 'UPC', 'Item Name'],
                $report['unused']->map(fn($r) => [$r['item_code'], $r['upc'], $r['item_name']])->all()
            );
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry-run: nada foi gravado.');
        }

        return self::SUCCESS;
    }
}
