<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $visionModel;
    protected string $textModel;

    // Dicionário de Medidas (Mantido)
    protected array $unitMap = [
        '/colher.*sopa/i' => 'Tablespoon', '/colher.*ch[aá]/i' => 'Teaspoon',
        '/x[ií]cara/i' => 'Cup', '/copo/i' => 'Cup', '/unidade/i' => 'Piece',
        '/fatia/i' => 'Slice', '/pote/i' => 'Container', '/garrafa/i' => 'Bottle',
        '/lata/i' => 'Can', '/biscoito/i' => 'Cookie', '/barra/i' => 'Bar',
        '/embalagem/i' => 'Package',
    ];

    public function __construct()
    {
        $this->host = rtrim(env('OLLAMA_HOST', 'http://100.89.133.69:11434'), '/');
        // Qwen 30B é ótimo para listar sequências numéricas
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:30b'); 
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest');
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        // PROMPT DE VETORES: Pede todos os números, sem pedir para escolher coluna
        $prompt = <<<EOT
Analise a imagem da Tabela Nutricional.

TAREFA 1: CABEÇALHO DA PORÇÃO
Extraia o texto exato da linha "Porções por embalagem" e da linha "Porção".

TAREFA 2: LINHAS NUTRICIONAIS (VETORES)
Para cada nutriente listado abaixo, extraia TODOS os números encontrados na mesma linha, lendo da ESQUERDA para a DIREITA.
Retorne os números em um array (lista). Exemplo: Se a linha for "Carboidratos 30g 10g 4%", retorne ["30", "10", "4"].

NUTRIENTES ALVO:
- Calorias (Valor Energético)
- Carboidratos
- Açúcares Totais
- Açúcares Adicionados
- Proteínas
- Gorduras Totais
- Gorduras Saturadas
- Gorduras Trans
- Fibra Alimentar
- Sódio
- Colesterol
- Cálcio, Ferro, Potássio, Vitamina A, C, D (se houver)

JSON ALVO:
{
  "header_servings_per_container": "string (texto completo)",
  "header_serving_info": "string (texto completo ex: 25g 2 1/2 xícaras)",
  
  "rows": {
    "calories": ["num1", "num2"...],
    "total_carb": ["num1", "num2"...],
    "total_sugars": [],
    "added_sugars": [],
    "sugar_alcohol": [],
    "protein": [],
    "total_fat": [],
    "sat_fat": [],
    "trans_fat": [],
    "fiber": [],
    "sodium": [],
    "cholesterol": [],
    "vitamin_d": [],
    "calcium": [],
    "iron": [],
    "potassium": [],
    "vitamin_a": [],
    "vitamin_c": []
  }
}
EOT;

        $response = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$response) return null;

        $jsonData = $this->robustJsonDecode($response);
        
        if (!$this->isValidNutritionalData($jsonData)) {
            Log::warning("Ollama: Retorno inválido ou vazio.");
            return null;
        }

        return $this->processStrategyRow($jsonData);
    }

    /**
     * Lógica de Seleção Inteligente via PHP
     */
    private function processStrategyRow(array $data): array
    {
        // 1. Processar Cabeçalho (Porções e Medidas)
        $servingsRaw = $data['header_servings_per_container'] ?? '';
        preg_match('/[0-9]+([.,][0-9]+)?/', $servingsRaw, $matches);
        $servingsPerContainer = str_replace(',', '.', $matches[0] ?? '1');

        $servingInfoRaw = $data['header_serving_info'] ?? '';
        // Separa "25g" de "2 1/2 xícaras"
        preg_match('/(\d+\s*[g|ml|kg|l]+)/i', $servingInfoRaw, $weightMatch);
        $servingWeight = $weightMatch[0] ?? trim($servingInfoRaw); // Fallback
        
        // Remove o peso para sobrar só a medida caseira
        $measureText = trim(str_replace($servingWeight, '', $servingInfoRaw));
        // Limpa parênteses
        $measureText = trim($measureText, "() ");
        
        $measureData = $this->parseHouseholdMeasure($measureText);

        // 2. Processar Linhas (A Mágica da Escolha de Coluna)
        $rows = $data['rows'] ?? [];
        $finalData = [
            'servings_per_container' => $servingsPerContainer,
            'serving_weight' => $servingWeight,
            'serving_size_quantity' => $measureData['qty'],
            'serving_size_unit' => $measureData['unit'],
        ];

        foreach ($rows as $key => $values) {
            // Limpa os valores para garantir que são números
            $numbers = array_map(function($val) {
                return (float) str_replace(',', '.', preg_replace('/[^0-9,\.-]/', '', (string)$val));
            }, $values);

            // Remove zeros vazios ou valores inválidos
            $numbers = array_values(array_filter($numbers, fn($n) => is_numeric($n)));

            $count = count($numbers);
            
            // LÓGICA DE DECISÃO DE COLUNA
            if ($count >= 3) {
                // Cenário: [100g, Porção, %VD] -> Ignora o primeiro (index 0)
                $val = $numbers[1]; // Porção
                $dv  = $numbers[2]; // %VD
            } elseif ($count == 2) {
                // Cenário: [Porção, %VD] -> Pega o primeiro
                $val = $numbers[0]; // Porção
                $dv  = $numbers[1]; // %VD
            } elseif ($count == 1) {
                // Cenário: Só tem o valor, sem %VD
                $val = $numbers[0];
                $dv  = 0;
            } else {
                $val = 0;
                $dv  = 0;
            }

            // Mapeia para o schema do banco
            $finalData[$key] = (string)$val;
            
            // Se tiver campo de DV no banco, salva
            if (in_array($key, ['total_carb', 'added_sugars', 'protein', 'total_fat', 'sat_fat', 'trans_fat', 'fiber', 'sodium', 'cholesterol'])) {
                $finalData[$key . '_dv'] = (string)$dv;
            }
        }

        return $finalData;
    }

    private function parseHouseholdMeasure(string $text): array
    {
        if (empty($text)) return ['qty' => '1', 'unit' => 'Unit'];

        $translatedUnit = 'Unit'; 
        foreach ($this->unitMap as $regex => $englishUnit) {
            if (preg_match($regex, $text)) {
                $translatedUnit = $englishUnit;
                break;
            }
        }

        $qty = '1';
        if (preg_match('/(\d+\s+\d+\/\d+|\d+\/\d+|\d+[\.,]\d+|\d+)/', $text, $matches)) {
            $qty = trim($matches[0]);
        }

        return ['qty' => $qty, 'unit' => $translatedUnit];
    }

    // --- Métodos de Suporte ---

    private function queryVision(string $model, string $prompt, string $image, int $timeout): ?string
    {
        try {
            $response = Http::timeout($timeout)->connectTimeout(5)->post("{$this->host}/api/chat", [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $prompt, 'images' => [$image]]],
                'stream' => false, 'format' => 'json',
                'options' => ['temperature' => 0.0, 'num_ctx' => 4096]
            ]);
            return $response->successful() ? $response->json('message.content') : null;
        } catch (\Exception $e) { Log::error($e->getMessage()); return null; }
    }

    private function robustJsonDecode(string $input): ?array
    {
        $clean = preg_replace('/```(?:json)?/i', '', $input);
        $clean = str_replace(['```', '`'], '', $clean);
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) $clean = $matches[0];
        $clean = preg_replace('/,\s*}/', '}', $clean);
        return json_decode($clean, true);
    }

    private function isValidNutritionalData(?array $data): bool
    {
        return isset($data['rows']) || isset($data['header_serving_info']);
    }
    
    public function completion(string $prompt, int $timeoutSeconds = 60): ?string { return null; }
}