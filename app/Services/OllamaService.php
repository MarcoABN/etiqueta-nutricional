<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;

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
        // IP do Tailscale do seu PC (Porta 8002)
        $this->host = rtrim(env('OLLAMA_HOST', 'http://127.0.0.1:8002'), '/');
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        $imageBinary = base64_decode($base64Image);
        $url = "{$this->host}/process-nutritional";

        try {
            Log::info("Enviando imagem para Worker Python: $url");

            // Timeout de 10 min para garantir que o Qwen tenha tempo
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

        // 1. Lista de Campos Simples (Nome no JSON == Nome no Banco)
        // Adicionadas todas as vitaminas/minerais e gorduras extras
        $fields = [
            'servings_per_container',
            'calories',
            
            'total_carb', 'total_carb_dv',
            'total_sugars', 
            'added_sugars', 'added_sugars_dv', 
            'sugar_alcohol',
            
            'protein', 'protein_dv',
            
            'total_fat', 'total_fat_dv',
            'sat_fat', 'sat_fat_dv',
            'trans_fat', 'trans_fat_dv',
            'mono_fat', 'poly_fat',
            
            'fiber', 'fiber_dv',
            'sodium', 'sodium_dv',
            'cholesterol', 'cholesterol_dv',

            // Micronutrientes (Conforme Migration)
            'vitamin_d', 'vitamin_a', 'vitamin_c', 'vitamin_e', 'vitamin_k',
            'thiamin', 'riboflavin', 'niacin', 'vitamin_b6', 'vitamin_b12',
            'folate', 'biotin', 'pantothenic_acid',
            'calcium', 'iron', 'potassium', 'phosphorus', 'iodine',
            'magnesium', 'zinc', 'selenium', 'copper', 'manganese',
            'chromium', 'molybdenum', 'chloride'
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $mapped[$field] = $this->cleanValue($data[$field]);
            }
        }

        // 2. Processamento da Porção (Ex: "30g (3 biscoitos)")
        if (!empty($data['serving_descriptor'])) {
            $raw = $data['serving_descriptor'];
            
            // Extrai peso numérico e unidade (g/ml)
            if (preg_match('/(\d+\s*[.,]?\s*\d*)\s*(g|ml|kg|l)/i', $raw, $weightMatch)) {
                $mapped['serving_weight'] = str_replace(',', '.', $weightMatch[1]) . strtolower($weightMatch[2]);
            }

            // Extrai medida caseira e unidade (xícara, colher)
            $measure = $this->parseHouseholdMeasure($raw);
            $mapped['serving_size_quantity'] = $measure['qty'];
            $mapped['serving_size_unit'] = $measure['unit'];
        }

        return $mapped;
    }

    private function cleanValue($val)
    {
        if (is_array($val)) return json_encode($val);
        
        // Remove tudo que não for número, ponto ou vírgula
        // (Remove "mg", "kcal", "%", "<", ">")
        $clean = preg_replace('/[^\d.,-]/', '', (string)$val);
        
        return $clean === '' ? '0' : str_replace(',', '.', $clean);
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