<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $visionModel;

    public function __construct()
    {
        $this->host = rtrim(env('OLLAMA_HOST', 'http://127.0.0.1:11434'), '/');
        // Recomendo models: qwen2.5-vl, llava-llama3 ou minicpm-v
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen2.5-vl:latest'); 
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 240): ?array
    {
        // Prompt Especializado para RDC 429/2020 (Padrão Novo com 100g e Porção)
        $prompt = <<<EOT
Você é um especialista em OCR de rótulos de alimentos brasileiros (ANVISA).
Analise esta imagem. O rótulo pode seguir o "Novo Padrão" com colunas separadas para "100g" e "Porção".

REGRAS CRÍTICAS:
1. FOCO NA COLUNA "PORÇÃO" (Serving Size). Ignore os valores da coluna "100g".
2. Se a tabela estiver dividida em dois blocos (esquerda/direita), LEIA AMBOS.
3. Se um valor for "0", "Zero", "Não contém" ou traço, retorne 0.
4. Para Sódio/Cálcio/Ferro: Se estiver em 'mg', mantenha. Se 'mcg', converta.

Retorne APENAS um JSON válido neste formato:
{
  "tamanho_porcao": "ex: 30g (3 biscoitos)",
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
EOT;

        $rawResponse = $this->query($this->visionModel, $prompt, $base64Image, true, $timeoutSeconds);

        if (!$rawResponse) {
            Log::error("Ollama: Sem resposta.");
            return null;
        }

        $jsonData = $this->robustJsonDecode($rawResponse);
        
        if (!$jsonData) {
            Log::error("Ollama: JSON inválido. Resposta: " . substr($rawResponse, 0, 100) . "...");
            return null;
        }

        return $this->mapData($jsonData);
    }

    private function robustJsonDecode(string $input): ?array
    {
        $clean = preg_replace('/```(?:json)?/i', '', $input);
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $clean = $matches[0];
        }
        // Remove vírgulas finais erradas (ex: "a":1, })
        $clean = preg_replace('/,\s*}/', '}', $clean);
        
        return json_decode($clean, true);
    }

    private function mapData(array $ptData): array
    {
        $clean = fn($k) => isset($ptData[$k]) 
            ? (float) str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $ptData[$k])) 
            : 0;

        return [
            'serving_info'      => $ptData['tamanho_porcao'] ?? 'Porção não detectada',
            'calories'          => $clean('calorias'),
            'total_carb'        => $clean('carboidratos'),
            'total_sugars'      => $clean('acucares_totais'),
            'added_sugars'      => $clean('acucares_adicionados'),
            'protein'           => $clean('proteinas'),
            'total_fat'         => $clean('gorduras_totais'),
            'sat_fat'           => $clean('gorduras_saturadas'),
            'trans_fat'         => $clean('gorduras_trans'),
            'fiber'             => $clean('fibra'),
            'sodium'            => $clean('sodio'),
            'calcium'           => $clean('calcio'),
        ];
    }

    private function query(string $model, string $prompt, string $image, bool $json, int $timeout)
    {
        try {
            $payload = [
                'model' => $model,
                'stream' => false,
                'messages' => [
                    [
                        'role' => 'user', 
                        'content' => $prompt,
                        'images' => [$image]
                    ]
                ],
                'options' => [
                    'temperature' => 0.1, // Baixa criatividade para focar nos números
                    'num_ctx' => 4096     // Contexto maior para imagens grandes
                ]
            ];

            if ($json) $payload['format'] = 'json';

            $response = Http::timeout($timeout)->post("{$this->host}/api/chat", $payload);

            return $response->json('message.content');
        } catch (\Exception $e) {
            Log::error("Ollama Connect Error: " . $e->getMessage());
            return null;
        }
    }
}