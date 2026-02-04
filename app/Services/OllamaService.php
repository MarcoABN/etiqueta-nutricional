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
        $this->host = rtrim(env('OLLAMA_HOST', 'http://127.0.0.1:11434'), '/');
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen2.5-vl:latest'); 
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest');
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 300): ?array
    {
        $prompt = <<<EOT
Analise esta Tabela Nutricional Brasileira (ANVISA).
Existem 3 colunas possíveis: "100g", "Porção" e "%VD".
EXTRAIA APENAS OS DADOS DA COLUNA "PORÇÃO" (SERVING SIZE). IGNORE A COLUNA 100g.

Retorne APENAS JSON cru, sem markdown, sem explicações:
{
  "tamanho_porcao": "ex: 30g (3 biscoitos)",
  "porcoes_embalagem": "numero ou aprox",
  "calorias": 0.0,
  "carboidratos": 0.0,
  "acucares_totais": 0.0,
  "acucares_adicionados": 0.0,
  "proteinas": 0.0,
  "gorduras_totais": 0.0,
  "gorduras_saturadas": 0.0,
  "gorduras_trans": 0.0,
  "fibra": 0.0,
  "sodio": 0.0,
  "calcio": 0.0
}
Use ponto para decimais. Se vazio, use 0.
EOT;

        $rawResponse = $this->query($this->visionModel, $prompt, $base64Image, true, $timeoutSeconds);

        if (!$rawResponse) {
            Log::error("Ollama Vision: Resposta vazia.");
            return null;
        }

        $jsonData = $this->robustJsonDecode($rawResponse);
        
        if (!$jsonData) {
            Log::error("Ollama Vision: JSON Falhou. Raw: " . substr($rawResponse, 0, 200));
            return null;
        }

        return $this->mapPortugueseKeysToEnglish($jsonData);
    }

    public function completion(string $prompt, int $timeoutSeconds = 60): ?string
    {
        return $this->query($this->textModel, $prompt, null, false, $timeoutSeconds);
    }

    private function robustJsonDecode(string $input): ?array
    {
        // 1. Remove Markdown code blocks
        $clean = preg_replace('/```(?:json)?/i', '', $input);
        
        // 2. Encontra o objeto JSON principal (do primeiro { até o último })
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $clean = $matches[0];
        }

        // 3. Corrige vírgulas finais inválidas
        $clean = preg_replace('/,\s*}/', '}', $clean);
        $clean = preg_replace('/,\s*]/', ']', $clean);

        // 4. Decodifica
        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("JSON Decode Error: " . json_last_error_msg() . " | Input: " . substr($clean, 0, 100));
        }

        return $decoded;
    }

    private function mapPortugueseKeysToEnglish(array $ptData): array
    {
        $cleanNum = function($key) use ($ptData) {
            if (!isset($ptData[$key])) return 0;
            // Remove tudo exceto números, ponto e vírgula
            $val = preg_replace('/[^0-9,\.-]/', '', (string)$ptData[$key]);
            // Troca vírgula por ponto
            $val = str_replace(',', '.', $val);
            return (float) $val;
        };

        return [
            'serving_info'          => $ptData['tamanho_porcao'] ?? '',
            'serving_per_container' => $ptData['porcoes_embalagem'] ?? null,
            'calories'          => $cleanNum('calorias'),
            'total_carb'        => $cleanNum('carboidratos'),
            'total_sugars'      => $cleanNum('acucares_totais'),
            'added_sugars'      => $cleanNum('acucares_adicionados'),
            'protein'           => $cleanNum('proteinas'),
            'total_fat'         => $cleanNum('gorduras_totais'),
            'sat_fat'           => $cleanNum('gorduras_saturadas'),
            'trans_fat'         => $cleanNum('gorduras_trans'),
            'fiber'             => $cleanNum('fibra'),
            'sodium'            => $cleanNum('sodio'),
            'calcium'           => $cleanNum('calcio'),
        ];
    }

    private function query(string $model, string $prompt, ?string $image, bool $json, int $timeout)
    {
        try {
            $payload = [
                'model' => $model,
                'stream' => false,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'options' => ['temperature' => 0.0, 'num_ctx' => 4096]
            ];

            if ($image) $payload['messages'][0]['images'] = [$image];
            if ($json) $payload['format'] = 'json';

            $response = Http::timeout($timeout)->post("{$this->host}/api/chat", $payload);

            if ($response->successful()) {
                return $response->json('message.content');
            }
            
            Log::error("Ollama HTTP Error: " . $response->status() . " - " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Ollama Exception: " . $e->getMessage());
            return null;
        }
    }
}