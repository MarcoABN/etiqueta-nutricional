<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;

    public function __construct()
    {
        // IP do Worker Python
        $this->host = rtrim(env('OLLAMA_HOST', 'http://127.0.0.1:8003'), '/');
    }

    /**
     * Processamento de Texto Genérico
     */
    public function completion(string $prompt, int $timeoutSeconds = 60): ?string
    {
        $url = "{$this->host}/completion";

        try {
            $response = Http::timeout($timeoutSeconds)
                ->post($url, [
                    'prompt' => $prompt,
                    'model'  => 'qwen2.5-vl:7b' 
                ]);

            if ($response->successful()) {
                return $response->json('text');
            }
            
            Log::warning("Worker Texto Falhou: " . $response->body());

        } catch (\Exception $e) {
            Log::error("Erro Conexão Texto: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Processamento de Imagem (Visão Nutricional)
     */
    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        $imageBinary = base64_decode($base64Image);
        $url = "{$this->host}/process-nutritional";

        try {
            Log::info("Enviando imagem para Worker Python: $url");

            $response = Http::timeout($timeoutSeconds)
                ->attach('file', $imageBinary, 'tabela.jpg')
                ->post($url);

            if ($response->successful()) {
                $json = $response->json();
                
                if (isset($json['analysis'])) {
                    // Log para debug no Laravel
                    Log::info("JSON Recebido do Python:", $json['analysis']);
                    return $this->mapJsonToDatabase($json['analysis']);
                }
            }
            
            Log::error("Erro Worker Python ({$response->status()}): " . $response->body());

        } catch (\Exception $e) {
            Log::error("Falha Conexão Worker: " . $e->getMessage());
        }

        return null;
    }

    private function mapJsonToDatabase(array $data): array
    {
        $mapped = [];

        // 1. Campos Numéricos (precisam de limpeza de caracteres especiais)
        $numericFields = [
            'servings_per_container',
            'calories',
            'total_carb', 'total_carb_dv',
            'total_sugars', 'added_sugars', 'added_sugars_dv', 
            'protein', 'protein_dv',
            'total_fat', 'total_fat_dv', 'sat_fat', 'sat_fat_dv',
            'trans_fat', 'trans_fat_dv',
            'fiber', 'fiber_dv',
            'sodium', 'sodium_dv', 
            'calcium', 'iron', 'potassium'
        ];

        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                $mapped[$field] = $this->cleanNumericValue($data[$field]);
            }
        }

        // 2. Campos de Texto (Ingredientes, Alergênicos) - NÃO limpar números/pontuação
        $textFields = [
            'ingredients_pt', 
            'allergens_contains_pt', 
            'allergens_may_contain_pt'
        ];

        foreach ($textFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $mapped[$field] = trim($data[$field]);
            }
        }

        // 3. Processamento da Porção (Mapeamento Direto do Python)
        
        // Peso (ex: 30g)
        if (!empty($data['serving_weight'])) {
             // Apenas garante padronização de virgula para ponto
             $mapped['serving_weight'] = str_replace([' ', ','], ['', '.'], $data['serving_weight']);
        }

        // Quantidade da Medida (ex: "1 1/2", "5", "12")
        // O Python já separou isso. Apenas pegamos o valor.
        if (isset($data['serving_measure_qty'])) {
            $mapped['serving_size_quantity'] = trim($data['serving_measure_qty']);
        }

        // Unidade da Medida (ex: "colher de sopa", "unidades")
        if (isset($data['serving_measure_unit'])) {
            $mapped['serving_size_unit'] = trim($data['serving_measure_unit']);
        }

        return $mapped;
    }

    /**
     * Limpeza estrita apenas para campos que DEVEM ser numéricos (Kcal, gramas, etc)
     */
    private function cleanNumericValue($val)
    {
        if (is_array($val)) return 0;
        if (is_null($val)) return null;

        $strVal = (string)$val;
        
        // Remove tudo que não for dígito, ponto, vírgula ou sinal de menos
        // Ex: "  12 g " -> "12"
        $clean = preg_replace('/[^\d.,-]/', '', $strVal);
        
        // Padroniza separador decimal
        $clean = str_replace(',', '.', $clean);

        return $clean === '' ? '0' : $clean;
    }
}