<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProductsFromCsv extends Command
{
    protected $signature = 'products:import {file : O caminho para o arquivo CSV}';
    protected $description = 'Importa dados nutricionais (Extracao Corrigida) tratando coluna extra e nans.';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return;
        }

        $this->info("Iniciando processamento de: {$filePath}");

        $handle = fopen($filePath, 'r');
        
        // 1. Lê o cabeçalho (Separador Pipe |)
        $header = fgetcsv($handle, 0, '|');
        
        // Limpeza de caracteres invisíveis no cabeçalho (BOM)
        if ($header) {
            $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
        } else {
            $this->error("Arquivo vazio ou formato inválido.");
            return;
        }
        
        // Mapeia Coluna => Índice
        $colMap = array_flip($header);
        
        // Validação
        if (!isset($colMap['CODPROD'])) {
            $this->error("Erro: Coluna CODPROD não encontrada no cabeçalho.");
            return;
        }

        $count = 0;
        $skipped = 0;
        $errors = 0;
        $batchSize = 500;

        $this->output->progressStart();
        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, '|')) !== false) {
                
                // --- TRATAMENTO CRÍTICO DE COLUNA EXTRA ---
                // O cabeçalho tem X colunas. Os dados têm X+1 colunas (Nome do arquivo no início).
                // Se detectarmos isso, removemos a primeira coluna da linha para alinhar.
                if (count($row) === count($header) + 1) {
                    array_shift($row); // Remove a coluna 0 (Filename)
                }
                
                // Se mesmo assim não bater, pula (linha quebrada)
                if (count($row) !== count($header)) {
                    continue; 
                }

                // Cria array associativo (NomeColuna => Valor)
                $data = [];
                foreach ($colMap as $key => $index) {
                    $data[$key] = $row[$index] ?? null;
                }

                $codprod = (int) $data['CODPROD'];
                
                if (!$codprod) {
                    $skipped++;
                    continue;
                }

                try {
                    $product = Product::where('codprod', $codprod)->first();

                    if ($product) {
                        
                        // Tratamentos de Texto
                        $descEn = $this->cleanText($data['DESCRICAO_EN'] ?? null);
                        $ingredients = str_replace(';', ',', $this->cleanText($data['INGREDIENTES'] ?? null));

                        // Tratamento de Alergênicos
                        $rawAlergicos = $this->cleanText($data['ALERGICOS'] ?? '');
                        $allergensParsed = $this->parseAllergens($rawAlergicos);

                        // Montagem dos Dados
                        $updateData = [
                            'product_name_en'       => $descEn,
                            'ingredients'           => $ingredients,
                            'allergens_contains'    => $allergensParsed['contains'],
                            'allergens_may_contain' => $allergensParsed['may_contain'],
                            
                            // Nutricionais (com limpeza de 'nan')
                            'servings_per_container' => $this->cleanNum($data['Servings_per_Container'] ?? null),
                            'serving_size_quantity'  => $this->cleanNum($data['Serving_Size_Quantity'] ?? null),
                            'serving_size_unit'      => $this->cleanText($data['Serving_Size_Quantity_Units'] ?? null),
                            'serving_weight'         => $this->cleanNum($data['Serving_Size_Weight'] ?? null),
                            
                            'calories'               => $this->cleanNum($data['Calories'] ?? null),
                            
                            'total_fat'              => $this->cleanNum($data['Total_Fat'] ?? null),
                            'total_fat_dv'           => $this->cleanNum($data['Total_Fat_%DV'] ?? null),
                            
                            'sat_fat'                => $this->cleanNum($data['Saturated_Fat'] ?? null),
                            'sat_fat_dv'             => $this->cleanNum($data['Saturated_Fat_%DV'] ?? null),
                            
                            'trans_fat'              => $this->cleanNum($data['Trans_Fat'] ?? null),
                            'trans_fat_dv'           => $this->cleanNum($data['Trans_Fat_%DV'] ?? null),
                            
                            'cholesterol'            => $this->cleanNum($data['Cholesterol'] ?? null),
                            'cholesterol_dv'         => $this->cleanNum($data['Cholesterol_%DV'] ?? null),
                            
                            'sodium'                 => $this->cleanNum($data['Sodium'] ?? null),
                            'sodium_dv'              => $this->cleanNum($data['Sodium_%DV'] ?? null),
                            
                            'total_carb'             => $this->cleanNum($data['Total_Carbohydrate'] ?? null),
                            'total_carb_dv'          => $this->cleanNum($data['Total_Carbohydrate_%DV'] ?? null),
                            
                            'fiber'                  => $this->cleanNum($data['Dietary_Fiber'] ?? null),
                            'fiber_dv'               => $this->cleanNum($data['Dietary_Fiber_%DV'] ?? null),
                            
                            'total_sugars'           => $this->cleanNum($data['Total_Sugars'] ?? null),
                            'added_sugars'           => $this->cleanNum($data['Includes_Added_Sugars'] ?? null),
                            'added_sugars_dv'        => $this->cleanNum($data['Includes_Added_Sugars_%DV'] ?? null),
                            
                            'protein'                => $this->cleanNum($data['Protein'] ?? null),
                            'protein_dv'             => $this->cleanNum($data['Protein_%DV'] ?? null),
                        ];

                        $product->update($updateData);
                        $count++;
                        
                    } else {
                        $skipped++;
                    }

                    $this->output->progressAdvance();

                    if (($count + $skipped) % $batchSize === 0) {
                        DB::commit();
                        DB::beginTransaction();
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->warn("Erro Cod {$codprod}: " . $e->getMessage());
                }
            }

            DB::commit();
            $this->output->progressFinish();

            $this->info("Importação Finalizada!");
            $this->info("Atualizados: {$count}");
            $this->warn("Ignorados (Não encontrados): {$skipped}");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Erro Fatal: " . $e->getMessage());
        } finally {
            fclose($handle);
        }
    }

    // Limpa texto e trata quebras de linha estranhas
    private function cleanText($text)
    {
        if (is_null($text) || strtolower($text) === 'nan') return null;
        
        // Remove espaços múltiplos e trim
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    // Limpa números e trata 'nan'
    private function cleanNum($val)
    {
        if (is_null($val)) return null;
        $val = trim($val);
        if ($val === '' || strtolower($val) === 'nan') return null;
        return $val;
    }

    // Parser de Alergênicos
    private function parseAllergens($text)
    {
        $result = ['contains' => null, 'may_contain' => null];
        if (empty($text)) return $result;

        $text = str_replace(';', ',', $text);

        if (preg_match('/Contains:?\s*(.*?)(?=\.?\s*(?:May contain|$))/i', $text, $matches)) {
            $result['contains'] = $this->finalizeText($matches[1]);
        }
        if (preg_match('/May contain:?\s*(.*)/i', $text, $matches)) {
            $result['may_contain'] = $this->finalizeText($matches[1]);
        }

        return $result;
    }

    private function finalizeText($str)
    {
        return trim(rtrim($str, '.'));
    }
}