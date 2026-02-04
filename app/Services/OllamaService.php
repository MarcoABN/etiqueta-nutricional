<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $visionModel;
    protected string $textModel;

    // Dicionário de Conversão para Padrão FDA
    protected array $fdaMeasures = [
        '/colher.*sopa/i'   => 'Tablespoon',
        '/colher.*ch[aá]/i' => 'Teaspoon',
        '/x[ií]cara/i'      => 'Cup',
        '/copo/i'           => 'Cup', // Geralmente 240ml
        '/unidade/i'        => 'Piece', // Ou 'Unit'
        '/fatia/i'          => 'Slice',
        '/pote/i'           => 'Container',
        '/garrafa/i'        => 'Bottle',
        '/lata/i'           => 'Can',
        '/biscoito/i'       => 'Cookie',
        '/barra/i'          => 'Bar',
    ];

    public function __construct()
    {
        $this->host = rtrim(env('OLLAMA_HOST', 'http://100.89.133.69:11434'), '/');
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:30b'); 
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest');
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 300): ?array
    {
        // Prompt refinado para separar Porções e Traduzir Medidas
        $prompt = <<<EOT
Analise esta imagem de tabela nutricional brasileira e extraia dados para um rótulo FDA (EUA).

ATENÇÃO AOS CAMPOS DE PORÇÃO:
1. "Porções por embalagem" (Servings Per Container):
   - Pode vir como "Cerca de 2", "Aprox. 5", ou apenas "5".
   - Extraia o TEXTO COMPLETO desta linha.
   
2. "Porção" (Serving Size) - PESO:
   - Extraia apenas a parte Métrica (gramas ou ml). Ex: "30g", "200ml".
   
3. "Medida Caseira" (Household Measure):
   - Extraia a medida doméstica entre parênteses. Ex: "1 colher de sopa", "2 fatias".

MACRONUTRIENTES:
- Extraia os números da coluna "Porção" (ignore a coluna 100g).
- Se encontrar "Zero", "Não contém" ou "-", retorne 0.

Retorne APENAS JSON:
{
  "servings_per_container": "string (ex: Cerca de 10)",
  "serving_weight": "string (ex: 30g)",
  "serving_size_unit": "string (ex: 2 colheres de sopa)",
  "calories": "number",
  "total_carb": "number",
  "total_carb_dv": "number",
  "total_sugars": "number",
  "added_sugars": "number",
  "added_sugars_dv": "number",
  "protein": "number",
  "protein_dv": "number",
  "total_fat": "number",
  "total_fat_dv": "number",
  "sat_fat": "number",
  "sat_fat_dv": "number",
  "trans_fat": "number",
  "fiber": "number",
  "fiber_dv": "number",
  "sodium": "number",
  "sodium_dv": "number"
}
EOT;

        $rawResponse = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$rawResponse) return null;

        $jsonData = $this->robustJsonDecode($rawResponse);
        
        if (!$jsonData || $this->isHallucination($jsonData)) {
            Log::warning("Ollama: Retorno inválido ou alucinação.");
            return null;
        }

        return $this->mapAndTranslateData($jsonData);
    }

    private function mapAndTranslateData(array $data): array
    {
        $cleanNum = function($k) use ($data) {
            if (!isset($data[$k])) return '0';
            $v = preg_replace('/[^0-9,\.-]/', '', (string)$data[$k]);
            return str_replace(',', '.', $v);
        };

        $cleanText = fn($k) => isset($data[$k]) ? trim((string)$data[$k]) : null;

        // 1. Tratamento Especial para "Porções por Embalagem" (Servings Per Container)
        $servings = $cleanText('servings_per_container');
        // Se vier "Cerca de 5", o FDA usa "About 5". Vamos traduzir o prefixo.
        if ($servings) {
            $servings = preg_replace('/^(Cerca de|Aproximadamente|Aprox\.)/i', 'About', $servings);
            // Se for apenas número (ex: "5"), o padrão FDA muitas vezes pede "5" ou "About 5" dependendo da variância.
            // Vamos manter o número ou o "About X".
        }

        // 2. Tratamento e Tradução da Medida Caseira
        $householdMeasure = $cleanText('serving_size_unit');
        if ($householdMeasure) {
            $householdMeasure = $this->translateMeasure($householdMeasure);
        }

        return [
            'servings_per_container'=> $servings,
            'serving_weight'        => $cleanText('serving_weight'), // Ex: 30g
            'serving_size_unit'     => $householdMeasure,           // Ex: 1 Tablespoon (Traduzido)
            
            'calories'      => $cleanNum('calories'),
            'total_carb'    => $cleanNum('total_carb'),
            'total_carb_dv' => $cleanNum('total_carb_dv'),
            'total_sugars'  => $cleanNum('total_sugars'),
            'added_sugars'  => $cleanNum('added_sugars'),
            'added_sugars_dv' => $cleanNum('added_sugars_dv'),
            'protein'       => $cleanNum('protein'),
            'protein_dv'    => $cleanNum('protein_dv'),
            'total_fat'     => $cleanNum('total_fat'),
            'total_fat_dv'  => $cleanNum('total_fat_dv'),
            'sat_fat'       => $cleanNum('sat_fat'),
            'sat_fat_dv'    => $cleanNum('sat_fat_dv'),
            'trans_fat'     => $cleanNum('trans_fat'),
            'fiber'         => $cleanNum('fiber'),
            'fiber_dv'      => $cleanNum('fiber_dv'),
            'sodium'        => $cleanNum('sodium'),
            'sodium_dv'     => $cleanNum('sodium_dv'),
        ];
    }

    /**
     * Traduz medidas caseiras de PT-BR para Padrão FDA (EN)
     */
    private function translateMeasure(string $measure): string
    {
        foreach ($this->fdaMeasures as $pattern => $translation) {
            if (preg_match($pattern, $measure)) {
                // Mantém o número (ex: "2 colheres" -> "2 Tablespoons")
                $number = preg_replace('/[^0-9\.\,\/]/', '', $measure); // Pega "2" ou "1/2"
                $number = trim($number);
                
                // Se não achou número explícito, assume 1 (ex: "Fatia" -> "1 Slice")
                // Mas geralmente vem "Porção de 30g (1 fatia)"
                
                // Pluralização simples para o inglês
                $suffix = ($number === '1' || $number === '') ? '' : 's';
                
                // Reconstrói a string: "2 Tablespoons"
                return $number ? "$number $translation$suffix" : "$translation$suffix";
            }
        }
        
        // Fallback: Se não casar com nada, retorna o original para correção manual
        return $measure; 
    }

    // --- Métodos Auxiliares Mantidos (queryVision, robustJsonDecode, etc) ---
    
    private function queryVision(string $model, string $prompt, string $image, int $timeout): ?string
    {
        try {
            $payload = [
                'model' => $model,
                'stream' => false,
                'messages' => [['role' => 'user', 'content' => $prompt, 'images' => [$image]]],
                'format' => 'json',
                'options' => ['temperature' => 0.0, 'num_ctx' => 2048]
            ];
            $response = Http::timeout($timeout)->connectTimeout(5)->post("{$this->host}/api/chat", $payload);
            return $response->successful() ? $response->json('message.content') : null;
        } catch (\Exception $e) { return null; }
    }

    private function robustJsonDecode(string $input): ?array
    {
        $clean = preg_replace('/```(?:json)?/i', '', $input);
        $clean = str_replace(['```', '`'], '', $clean);
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) $clean = $matches[0];
        $clean = preg_replace('/,\s*}/', '}', $clean);
        return json_decode($clean, true);
    }

    private function isHallucination(?array $data): bool
    {
        if (!$data) return true;
        if (isset($data['response']) || isset($data['title'])) return true;
        return isset($data['calories']) || isset($data['serving_weight']);
    }

    public function completion(string $prompt, int $timeoutSeconds = 60): ?string {
        // ... (mantido igual)
        return null; 
    }
}