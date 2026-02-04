<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $visionModel;
    protected string $textModel;

    // Dicionário de Tradução (Medidas Caseiras)
    protected array $unitMap = [
        '/colher.*sopa/i'   => 'Tablespoon',
        '/colher.*ch[aá]/i' => 'Teaspoon',
        '/colher.*sobremesa/i' => 'Dessert Spoon',
        '/x[ií]cara/i'      => 'Cup',
        '/copo/i'           => 'Cup', 
        '/unidade/i'        => 'Piece',
        '/fatia/i'          => 'Slice',
        '/pote/i'           => 'Container',
        '/garrafa/i'        => 'Bottle',
        '/lata/i'           => 'Can',
        '/biscoito/i'       => 'Cookie',
        '/barra/i'          => 'Bar',
        '/embalagem/i'      => 'Package',
    ];

    public function __construct()
    {
        $this->host = rtrim(env('OLLAMA_HOST', 'http://100.89.133.69:11434'), '/');
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:30b'); 
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest');
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        // PROMPT REFATORADO: Lógica de "Header Matching" (Mais visual e humano)
        $prompt = <<<EOT
Analise esta Tabela Nutricional.

PASSO 1: IDENTIFIQUE A PORÇÃO
Localize o texto "Porção" no topo. Ex: "Porção: 25 g (2 1/2 xícaras)".
- Separe o PESO (25 g).
- Separe a MEDIDA CASEIRA (2 1/2 xícaras).

PASSO 2: IDENTIFIQUE A COLUNA CORRETA
A tabela abaixo tem colunas de números.
- Procure o cabeçalho da coluna que corresponde ao PESO da porção (ex: procure a coluna com título "25 g").
- Se não houver título exato, use a coluna do MEIO (se houver 3) ou a PRIMEIRA (se houver 2).
- IGNORE a coluna "100 g" ou "100 ml".

PASSO 3: EXTRAIA OS DADOS DESSA COLUNA ESPECÍFICA
Para cada nutriente (Carboidratos, Proteínas, Sódio...), extraia o valor numérico que está NA COLUNA IDENTIFICADA no Passo 2.
Extraia também o %VD da última coluna.

JSON ALVO (Retorne APENAS JSON):
{
  "servings_per_container": "string (texto completo da linha 'Porções por embalagem')",
  "serving_weight": "string (ex: 25 g)",
  "serving_measure_text": "string (ex: 2 1/2 xícaras)",
  "calories": "number",
  "total_carb": "number",
  "total_carb_dv": "number",
  "total_sugars": "number",
  "added_sugars": "number",
  "added_sugars_dv": "number",
  "sugar_alcohol": "number",
  "protein": "number",
  "protein_dv": "number",
  "total_fat": "number",
  "total_fat_dv": "number",
  "sat_fat": "number",
  "sat_fat_dv": "number",
  "trans_fat": "number",
  "trans_fat_dv": "number",
  "fiber": "number",
  "fiber_dv": "number",
  "sodium": "number",
  "sodium_dv": "number",
  "cholesterol": "number",
  "cholesterol_dv": "number",
  "vitamin_a": "number",
  "vitamin_c": "number",
  "vitamin_d": "number",
  "calcium": "number",
  "iron": "number",
  "potassium": "number"
}
EOT;

        $response = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$response) return null;

        $jsonData = $this->robustJsonDecode($response);
        
        if (!$this->isValidNutritionalData($jsonData)) {
            Log::warning("Ollama: JSON retornado não contém dados nutricionais válidos.");
            // Opcional: Logar o raw response para debug se precisar
            // Log::debug("Raw Response: " . substr($response, 0, 200));
            return null;
        }

        return $this->processAndSanitizeData($jsonData);
    }

    private function processAndSanitizeData(array $data): array
    {
        // 1. Processar Porções por Embalagem
        $servingsRaw = $data['servings_per_container'] ?? '';
        preg_match('/[0-9]+([.,][0-9]+)?/', $servingsRaw, $matches);
        $servingsPerContainer = $matches[0] ?? '1'; 
        $servingsPerContainer = str_replace(',', '.', $servingsPerContainer);

        // 2. Processar Medida Caseira
        $measureText = $data['serving_measure_text'] ?? '';
        $measureData = $this->parseHouseholdMeasure($measureText);

        // 3. Limpeza Numérica Robustecida
        $cleanNum = function($key) use ($data) {
            if (!isset($data[$key])) return '0';
            
            // Remove letras, espaços e símbolos irrelevantes, mantendo números, vírgula, ponto e traço
            $val = preg_replace('/[^0-9,\.-]/', '', (string)$data[$key]);
            
            // Troca vírgula por ponto para padronização
            $val = str_replace(',', '.', $val);
            
            // Verifica se resultou em string vazia, apenas ponto ou traço isolado
            if ($val === '' || $val === '.' || $val === '-') return '0';
            
            return $val;
        };

        $cleanText = fn($key) => isset($data[$key]) ? trim((string)$data[$key]) : null;

        return [
            'servings_per_container' => $servingsPerContainer, 
            'serving_weight'        => $cleanText('serving_weight'),
            'serving_size_quantity' => $measureData['qty'], 
            'serving_size_unit'     => $measureData['unit'],
            
            // Macros
            'calories'          => $cleanNum('calories'),
            'total_carb'        => $cleanNum('total_carb'),
            'total_carb_dv'     => $cleanNum('total_carb_dv'),
            'total_sugars'      => $cleanNum('total_sugars'),
            'added_sugars'      => $cleanNum('added_sugars'),
            'added_sugars_dv'   => $cleanNum('added_sugars_dv'),
            'sugar_alcohol'     => $cleanNum('sugar_alcohol'),
            'protein'           => $cleanNum('protein'),
            'protein_dv'        => $cleanNum('protein_dv'),
            'total_fat'         => $cleanNum('total_fat'),
            'total_fat_dv'      => $cleanNum('total_fat_dv'),
            'sat_fat'           => $cleanNum('sat_fat'),
            'sat_fat_dv'        => $cleanNum('sat_fat_dv'),
            'trans_fat'         => $cleanNum('trans_fat'),
            'trans_fat_dv'      => $cleanNum('trans_fat_dv'),
            'fiber'             => $cleanNum('fiber'),
            'fiber_dv'          => $cleanNum('fiber_dv'),
            'sodium'            => $cleanNum('sodium'),
            'sodium_dv'         => $cleanNum('sodium_dv'),
            'cholesterol'       => $cleanNum('cholesterol'),
            'cholesterol_dv'    => $cleanNum('cholesterol_dv'),
            'vitamin_d'         => $cleanNum('vitamin_d'),
            'calcium'           => $cleanNum('calcium'),
            'iron'              => $cleanNum('iron'),
            'potassium'         => $cleanNum('potassium'),
            'vitamin_a'         => $cleanNum('vitamin_a'),
            'vitamin_c'         => $cleanNum('vitamin_c'),
        ];
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
        // Regex aprimorada para capturar "2 1/2" ou "2.5" ou "2" no início da string
        // Captura o primeiro grupo numérico que encontrar
        if (preg_match('/^(\d+\s+\d+\/\d+|\d+\/\d+|\d+[\.,]\d+|\d+)/', trim($text), $matches)) {
            $qty = trim($matches[0]);
        }

        return [
            'qty' => $qty,
            'unit' => $translatedUnit
        ];
    }

    private function queryVision(string $model, string $prompt, string $image, int $timeout): ?string
    {
        try {
            $response = Http::timeout($timeout)->connectTimeout(5)->post("{$this->host}/api/chat", [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $prompt, 'images' => [$image]]],
                'stream' => false, 
                'format' => 'json',
                'options' => ['temperature' => 0.0, 'num_ctx' => 2048]
            ]);
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

    private function isValidNutritionalData(?array $data): bool
    {
        if (!$data) return false;
        // Verifica se pelo menos 1 campo importante veio preenchido (não nulo e não zero "string")
        // Mas cuidado: "0" é válido. "null" é falha de leitura.
        return isset($data['calories']) || isset($data['serving_weight']) || isset($data['total_carb']);
    }

    public function completion(string $prompt, int $timeoutSeconds = 60): ?string { return null; }
}