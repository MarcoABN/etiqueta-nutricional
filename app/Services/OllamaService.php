<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $visionModel;
    protected string $textModel;

    public function __construct()
    {
        $this->host = rtrim(env('OLLAMA_HOST', 'http://100.89.133.69:11434'), '/');
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:8b'); 
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest'); // Modelo leve para tradução
    }

    /**
     * Extrai dados nutricionais (OCR) da imagem
     */
    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        $prompt = <<<EOT
Analise esta Tabela Nutricional. Extraia os dados numéricos.
Se a imagem não for uma tabela nutricional, retorne JSON vazio.

REGRAS:
1. Retorne APENAS um objeto JSON.
2. Identifique Porção, Calorias, Macros, %VD e Vitaminas.
3. Se um valor for "zero" ou traço "-", retorne "0".

JSON ALVO:
{
  "serving_weight": "ex: 30g",
  "serving_size_quantity": "ex: 1",
  "serving_size_unit": "ex: colher",
  "servings_per_container": "ex: aprox 5",
  "calories": "0",
  "total_carb": "0",
  "total_carb_dv": "0",
  "total_sugars": "0",
  "added_sugars": "0",
  "added_sugars_dv": "0",
  "sugar_alcohol": "0",
  "protein": "0",
  "protein_dv": "0",
  "total_fat": "0",
  "total_fat_dv": "0",
  "sat_fat": "0",
  "sat_fat_dv": "0",
  "trans_fat": "0",
  "trans_fat_dv": "0",
  "fiber": "0",
  "fiber_dv": "0",
  "sodium": "0",
  "sodium_dv": "0",
  "cholesterol": "0",
  "cholesterol_dv": "0",
  "vitamin_a": "0",
  "vitamin_c": "0",
  "vitamin_d": "0",
  "calcium": "0",
  "iron": "0",
  "potassium": "0"
}
EOT;

        $response = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$response) return null;

        $jsonData = $this->robustJsonDecode($response);
        
        // VALIDAÇÃO ANTI-ALUCINAÇÃO
        if (!$this->isValidNutritionalData($jsonData)) {
            Log::warning("Ollama: Alucinação detectada (Conteúdo inválido). Retorno ignorado.");
            return null;
        }

        return $this->sanitizeData($jsonData);
    }

    /**
     * MÉTODO RESTAURADO: Usado pelo GeminiFdaTranslator para tradução simples
     */
    public function completion(string $prompt, int $timeoutSeconds = 60): ?string
    {
        try {
            $payload = [
                'model' => $this->textModel, // Usa gemma2 ou similar (mais rápido)
                'stream' => false,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'options' => ['temperature' => 0.0, 'num_ctx' => 2048]
            ];

            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout(5)
                ->post("{$this->host}/api/chat", $payload);

            if ($response->successful()) {
                return $response->json('message.content');
            }
            
            Log::error("Ollama Text Error: " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error("Ollama Text Exception: " . $e->getMessage());
            return null;
        }
    }

    private function queryVision(string $model, string $prompt, string $image, int $timeout): ?string
    {
        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(5)
                ->post("{$this->host}/api/chat", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt, 'images' => [$image]]
                    ],
                    'stream' => false,
                    'format' => 'json',
                    'options' => [
                        'temperature' => 0.0,
                        'num_ctx' => 4096,
                    ]
                ]);

            if ($response->successful()) {
                return $response->json('message.content');
            }
            
            Log::error("Ollama Vision Error: {$response->status()}");
            return null;
        } catch (\Exception $e) {
            Log::error("Ollama Vision Exception: " . $e->getMessage());
            return null;
        }
    }

    private function robustJsonDecode(string $input): ?array
    {
        $clean = preg_replace('/```(?:json)?/i', '', $input);
        $clean = str_replace(['```', '`'], '', $clean);
        
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $clean = $matches[0];
        }
        
        $clean = preg_replace('/,\s*}/', '}', $clean);
        return json_decode($clean, true);
    }

    /**
     * Verifica se o JSON parece mesmo uma tabela nutricional
     * Evita o erro "The Great American Road Trip"
     */
    private function isValidNutritionalData(?array $data): bool
    {
        if (!$data) return false;

        // Se tiver título ou conteúdo de texto longo, é alucinação
        if (isset($data['title']) || isset($data['content']) || isset($data['intro'])) {
            return false;
        }

        // Verifica se tem pelo menos 2 campos chaves de nutrição
        $keys = array_keys($data);
        $requiredMatches = 0;
        $indicators = ['calories', 'fat', 'protein', 'carb', 'sodio', 'sodium', 'porcao', 'serving'];
        
        foreach ($keys as $key) {
            foreach ($indicators as $ind) {
                if (str_contains(strtolower($key), $ind)) {
                    $requiredMatches++;
                }
            }
        }

        return $requiredMatches >= 2;
    }

    private function sanitizeData(array $data): array
    {
        $cleanNum = function($key) use ($data) {
            if (!isset($data[$key])) return null;
            $val = preg_replace('/[^0-9,\.-]/', '', (string)$data[$key]);
            $val = str_replace(',', '.', $val);
            return ($val === '' || $val === '.') ? null : $val;
        };

        $cleanText = fn($key) => isset($data[$key]) ? trim((string)$data[$key]) : null;

        return [
            'serving_weight'        => $cleanText('serving_weight'),
            'serving_size_quantity' => $cleanText('serving_size_quantity'),
            'serving_size_unit'     => $cleanText('serving_size_unit'),
            'servings_per_container'=> $cleanText('servings_per_container'),
            
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
            
            // Micros
            'vitamin_d'         => $cleanNum('vitamin_d'),
            'calcium'           => $cleanNum('calcium'),
            'iron'              => $cleanNum('iron'),
            'potassium'         => $cleanNum('potassium'),
            'vitamin_a'         => $cleanNum('vitamin_a'),
            'vitamin_c'         => $cleanNum('vitamin_c'),
        ];
    }
}