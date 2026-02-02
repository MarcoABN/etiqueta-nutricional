<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportProductsFromCsv extends Command
{
    // O nome do comando que você vai rodar no terminal
    protected $signature = 'products:import {file : O caminho para o arquivo CSV}';

    protected $description = 'Importa produtos do CSV do WinThor (Cod;Descricao;EAN)';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return;
        }

        $this->info("Iniciando importação de: {$filePath}");

        // Abre o arquivo em modo leitura
        $handle = fopen($filePath, 'r');
        
        // Lê o cabeçalho para ignorá-lo (CODPROD;DESCRICAO;CODAUXILIAR)
        fgetcsv($handle, 0, ';');

        $count = 0;
        $errors = 0;

        // Barra de progresso visual
        $this->output->progressStart();

        DB::beginTransaction(); // Faz tudo ou nada (segurança)

        try {
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                // Estrutura do seu CSV:
                // [0] => CODPROD (Ex: 6581)
                // [1] => DESCRICAO (Ex: ABRACADEIRA...)
                // [2] => CODAUXILIAR (Ex: 789...) ou vazio

                // 1. Limpeza e Conversão de Encodificação (WinThor costuma ser Latin1)
                $codprod = (int) trim($row[0]);
                $nome = mb_convert_encoding(trim($row[1]), 'UTF-8', 'ISO-8859-1');
                
                // Trata código de barras vazio como NULL para não violar a regra de Unique
                $barcode = isset($row[2]) ? trim($row[2]) : null;
                if ($barcode === '') {
                    $barcode = null;
                }

                try {
                    // 2. Cria ou Atualiza (Upsert)
                    // Procura pelo 'codprod'. Se achar, atualiza o nome e barras. Se não, cria novo.
                    $product = Product::updateOrCreate(
                        ['codprod' => $codprod], 
                        [
                            'product_name' => $nome,
                            'barcode' => $barcode,
                            // Campos obrigatórios defaults
                            'cholesterol' => '0', 
                            // O ID (UUID) é gerado automaticamente pelo Model se for criar
                        ]
                    );

                    $count++;
                    $this->output->progressAdvance();

                } catch (\Exception $e) {
                    // Captura erro específico de linha (ex: barcode duplicado em produtos diferentes)
                    $this->newLine();
                    $this->warn("Erro no produto Cód {$codprod}: " . $e->getMessage());
                    $errors++;
                }
            }

            DB::commit(); // Salva tudo no banco
            $this->output->progressFinish();
            $this->success("Importação concluída! Importados/Atualizados: {$count}. Erros: {$errors}.");

        } catch (\Exception $e) {
            DB::rollBack(); // Desfaz tudo se der erro grave
            $this->error("Erro fatal na importação: " . $e->getMessage());
        } finally {
            fclose($handle);
        }
    }
}