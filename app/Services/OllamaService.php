<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;

    // Mapeamento de Regex (Português) -> Abreviação FDA (Inglês)
    // Fonte: FDA Guidance for Industry: Nutrition Labeling Manual
    protected array $unitMap = [
        // Colheres
        '/colher.*sopa/i'   => 'Tbsp', // Tablespoon -> Tbsp
        '/colher.*ch[aá]/i' => 'tsp',  // Teaspoon -> tsp
        '/colher/i'         => 'Tbsp', // Default para Tbsp se não especificar

        // Copos e Líquidos
        '/x[ií]c/i'         => 'cup',  // Cup geralmente fica em minúsculo ou 'cup'
        '/copo/i'           => 'glass', // Glass (uso comum, mas cup é melhor para medida padrão)
        '/ml/i'             => 'mL',   // Mililitros
        '/litro/i'          => 'L',

        // Unidades Sólidas
        '/fatia/i'          => 'slice',
        '/unidade/i'        => 'piece',
        '/biscoito/i'       => 'cookie',
        '/bolacha/i'        => 'cracker',
        
        // Embalagens
        '/embalagem/i'      => 'pkg',  // Package -> pkg
        '/pacote/i'         => 'pkg',
        '/pote/i'           => 'pkg',
        '/lata/i'           => 'can',
        '/garrafa/i'        => 'bottle',
        
        // Pesos (caso apareçam no descritor)
        '/onça/i'           => 'oz',
        '/libra/i'          => 'lb',
    ];

    public function __construct()
    {
        // IP do Tailscale do seu PC (Porta 8002 do Worker Python)
        $this->host = rtrim(env('OLLAMA_HOST', 'http://127.0.0.1:8002'), '/');
    }

    /**
     * Método para processamento de texto (Tradução/Correção)
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

        // Mapeamento direto de campos
        $fields = [
            'servings_per_container',
            'calories',
            'total_carb', 'total_carb_dv',
            'total_sugars', 'added_sugars', 'added_sugars_dv', 'sugar_alcohol',
            'protein', 'protein_dv',
            'total_fat', 'total_fat_dv', 'sat_fat', 'sat_fat_dv',
            'trans_fat', 'trans_fat_dv', 'mono_fat', 'poly_fat',
            'fiber', 'fiber_dv',
            'sodium', 'sodium_dv', 'cholesterol', 'cholesterol_dv',
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

        // Processamento da Porção com conversão para Abreviação FDA
        if (!empty($data['serving_descriptor'])) {
            $raw = $data['serving_descriptor'];
            
            // 1. Extrai peso numérico (ex: 20g)
            if (preg_match('/(\d+\s*[.,]?\s*\d*)\s*(g|ml|kg|l)/i', $raw, $weightMatch)) {
                $mapped['serving_weight'] = str_replace(',', '.', $weightMatch[1]) . strtolower($weightMatch[2]);
            }

            // 2. Extrai medida caseira já convertida para abreviação (ex: "1 Tbsp")
            $measure = $this->parseHouseholdMeasure($raw);
            $mapped['serving_size_quantity'] = $measure['qty'];
            $mapped['serving_size_unit'] = $measure['unit']; // Aqui já virá 'Tbsp', 'cup', 'slice'
        }

        return $mapped;
    }

    private function cleanValue($val)
    {
        if (is_array($val)) return json_encode($val);
        $clean = preg_replace('/[^\d.,-]/', '', (string)$val);
        return $clean === '' ? '0' : str_replace(',', '.', $clean);
    }

    private function parseHouseholdMeasure(string $text): array
    {
        $translatedUnit = 'piece'; // Default genérico seguro (unidade/peça)
        
        foreach ($this->unitMap as $regex => $fdaAbbreviation) {
            if (preg_match($regex, $text)) {
                $translatedUnit = $fdaAbbreviation;
                break;
            }
        }

        $qty = '1';
        // Procura frações (1/2, 1.5) ou inteiros
        if (preg_match('/(\d+\s+\d+\/\d+|\d+\/\d+|\d+[.,]\d+|\d+)/', $text, $matches)) {
            $qty = str_replace(',', '.', $matches[0]);
        }
        
        return ['qty' => $qty, 'unit' => $translatedUnit];
    }
}