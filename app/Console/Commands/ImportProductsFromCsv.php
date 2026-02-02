<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProductsFromCsv extends Command
{
    protected $signature = 'products:import {file : O caminho para o arquivo CSV}';
    protected $description = 'Importa produtos do CSV do WinThor (Cod;Descricao;EAN) em lotes';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return;
        }

        $this->info("Iniciando importação de: {$filePath}");

        $handle = fopen($filePath, 'r');

        // Ignora cabeçalho
        fgetcsv($handle, 0, ';');

        $count = 0;
        $errors = 0;
        $batchSize = 1000; // Salva a cada 1000 registros para não estourar a memória

        $this->output->progressStart();

        // Inicia a primeira transação
        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                // Validação básica de colunas
                if (count($row) < 2)
                    continue;

                $codprod = (int) trim($row[0]);
                // Converte de ISO-8859-1 (WinThor) para UTF-8
                $nome = mb_convert_encoding(trim($row[1]), 'UTF-8', 'ISO-8859-1');

                $barcode = isset($row[2]) ? trim($row[2]) : null;

                // Verifica se é vazio OU se é zero (simples ou múltiplo '000')
                if (empty($barcode) || $barcode === '0' || $barcode === '00' || $barcode === '000') {
                    $barcode = null;
                }

                try {
                    Product::updateOrCreate(
                        ['codprod' => $codprod],
                        [
                            'product_name' => $nome,
                            'barcode' => $barcode,
                            'cholesterol' => '0',
                        ]
                    );

                    $count++;
                    $this->output->progressAdvance();

                    // --- MÁGICA PARA NÃO ESTOURAR MEMÓRIA ---
                    // A cada 1000 registros, comita e abre nova transação
                    if ($count % $batchSize === 0) {
                        DB::commit();
                        DB::beginTransaction();
                    }

                } catch (\Exception $e) {
                    // Se der erro num produto específico, não para tudo, apenas loga
                    // Mas precisamos garantir que a transação continue válida
                    $this->newLine();
                    $this->warn("Erro no produto Cód {$codprod}: " . $e->getMessage());
                    $errors++;
                }
            }

            // Comita o restante que sobrou no último lote
            DB::commit();

            $this->output->progressFinish();

            // CORREÇÃO: Usar info() em vez de success()
            $this->info("Importação concluída com sucesso!");
            $this->info("Total Processado: {$count}");
            $this->info("Total com Erros: {$errors}");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Erro fatal na importação: " . $e->getMessage());
        } finally {
            fclose($handle);
        }
    }
}