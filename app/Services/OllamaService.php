<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    // Modelo padrão definido para garantir consistência
    protected string $model = 'qwen3-vl:8b';

    protected array $unitMap = [
        '/x[ií]c/i'         => 'Cup',
        '/copo/i'           => 'Cup',
        '/colher.*sopa/i'   => 'Tablespoon',
        '/colher.*ch[aá]/i' => 'Teaspoon',
        '/colher/i'         => 'Tablespoon',
        '/fatia/i'          => 'Slice',
        '/unidade/i'        => 'Piece',      
        '/embalagem/i'      => 'Package',
    ];

    public function __construct()
    {
        // Pega o HOST do .env (ajustado para Docker ou Local)
        $this->host = rtrim(env('OLLAMA_HOST', 'http://127.0.0.1:8002'), '/');
    }

    /**
     * Processamento de Texto Geral
     */
    public function completion(string $prompt, int $timeoutSeconds = 60): ?string
    {
        $url = "{$this->host}/completion";

        try {
            $response = Http::timeout($timeoutSeconds)
                ->post($url, [
                    'prompt' => $prompt,
                    'model'  => $this->model 
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
     * Processamento de Imagem (Visão)
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

        // 1. CAMPOS NUMÉRICOS (Precisam de limpeza de caracteres)
        $numericFields = [
            'servings_per_container',
            'calories',
            'total_carb', 'total_carb_dv',
            'total_sugars', 'added_sugars', 'added_sugars_dv', 'sugar_alcohol',
            'protein', 'protein_dv',
            'total_fat', 'total_fat_dv', 'sat_fat', 'sat_fat_dv',
            'trans_fat', 'trans_fat_dv', 'mono_fat', 'poly_fat',
            'fiber', 'fiber_dv',
            'sodium', 'sodium_dv', 'cholesterol', 'cholesterol_dv',
            // Micronutrientes
            'vitamin_d', 'vitamin_a', 'vitamin_c', 'vitamin_e', 'vitamin_k', 'vitamin_b12',
            'thiamin', 'riboflavin', 'niacin', 'vitamin_b6', 'folate', 'biotin', 
            'pantothenic_acid', 'phosphorus', 'iodine', 'magnesium', 'zinc', 
            'selenium', 'copper', 'manganese', 'chromium', 'molybdenum', 'chloride',
            'calcium', 'iron', 'potassium'
        ];

        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                $mapped[$field] = $this->cleanNumericValue($data[$field]);
            }
        }

        // 2. CAMPOS DE TEXTO (Ingredientes e Alergênicos - CORRIGIDO)
        $textFields = [
            'ingredients_pt', 
            'allergens_contains_pt', 
            'allergens_may_contain_pt'
        ];

        foreach ($textFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $value = $data[$field];

                // CORREÇÃO DO ERRO: Se a IA mandar um array ["A", "B"], converte para string "A, B"
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                // Agora o trim recebe sempre string
                $mapped[$field] = trim((string)$value);
            }
        }

        // 3. Processamento da Porção
        if (!empty($data['serving_descriptor'])) {
            $raw = $data['serving_descriptor'];
            
            if (preg_match('/(\d+\s*[.,]?\s*\d*)\s*(g|ml|kg|l)/i', $raw, $weightMatch)) {
                $mapped['serving_weight'] = str_replace(',', '.', $weightMatch[1]) . strtolower($weightMatch[2]);
            }

            $measure = $this->parseHouseholdMeasure((string)$raw); // Cast string por segurança
            $mapped['serving_size_quantity'] = $measure['qty'];
            $mapped['serving_size_unit'] = $measure['unit'];
        }

        return $mapped;
    }

    /**
     * Limpa apenas valores numéricos
     */
    private function cleanNumericValue($val)
    {
        if (is_array($val)) return json_encode($val);
        
        $strVal = (string)$val;

        if (preg_match('/(zero|n[ãa]o cont[ée]m|tra[çc]os|isento)/i', $strVal)) {
            return '0';
        }

        $clean = preg_replace('/[^\d.,-]/', '', $strVal);
        return $clean === '' ? null : str_replace(',', '.', $clean);
    }

    private function parseHouseholdMeasure(string $text): array
    {
        $translatedUnit = 'Unit'; 
        foreach ($this->unitMap as $regex => $englishUnit) {
            if (preg_match($regex, $text)) {
                $translatedUnit = $englishUnit;
                break;
            }
        }
        
        $qty = '1';
        if (preg_match('/(\d+\s+\d+\/\d+|\d+\/\d+|\d+[.,]\d+|\d+)/', $text, $matches)) {
            $qty = str_replace(',', '.', $matches[0]);
        }
        
        return ['qty' => $qty, 'unit' => $translatedUnit];
    }
}