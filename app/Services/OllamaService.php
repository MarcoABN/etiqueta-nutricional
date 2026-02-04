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
        $this->visionModel = env('OLLAMA_MODEL', 'qwen3-vl:8b');
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma3:4b');
    }

    /**
     * MOTOR DE VISÃO: OCR Robusto com Tratamento de Falhas
     */
    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 180): ?array
    {
        // Prompt reforçado com exemplo de estrutura (Few-Shot)
        $prompt = <<<EOT
Analise a imagem da Tabela Nutricional. Extraia APENAS os números.
Não invente dados. Se não estiver visível, use null.

Responda ESTRITAMENTE com este formato JSON (sem markdown, sem texto antes):

{
  "porcoes_embalagem": "aprox. 8",
  "tamanho_porcao": "30g (5 biscoitos)",
  "calorias": 140,
  "carboidratos": 22,
  "acucares_totais": 10,
  "acucares_adicionados": 10,
  "proteinas": 2.5,
  "gorduras_totais": 5.0,
  "gorduras_saturadas": 2.1,
  "gorduras_trans": 0,
  "fibra": 1.2,
  "sodio": 85,
  "calcio": 0,
  "ferro": 0,
  "potassio": 0,
  "vd_carboidratos": 7,
  "vd_proteinas": 3,
  "vd_gorduras_totais": 9,
  "vd_gorduras_saturadas": 10,
  "vd_fibra": 5,
  "vd_sodio": 4
}

Se o valor for "Zero" ou "Não contém", retorne 0.
Extraia os dados da imagem agora:
EOT;

        // 1. Consulta a IA
        $rawResponse = $this->query($this->visionModel, $prompt, $base64Image, true, $timeoutSeconds);

        if (!$rawResponse) {
            Log::error("Ollama: Resposta vazia da IA.");
            return null;
        }

        // 2. Sanitização e Decodificação Robusta
        $jsonData = $this->robustJsonDecode($rawResponse);

        if (!$jsonData) {
            Log::error("Ollama: Falha ao decodificar JSON. Raw: " . substr($rawResponse, 0, 500));
            return null;
        }

        // 3. Mapeamento
        return $this->mapPortugueseKeysToEnglish($jsonData);
    }

    /**
     * MOTOR DE TEXTO
     */
    public function completion(string $prompt, int $timeoutSeconds = 30): ?string
    {
        return $this->query($this->textModel, $prompt, null, false, $timeoutSeconds);
    }

    /**
     * Tenta salvar o JSON mesmo que a IA tenha errado a sintaxe
     */
    private function robustJsonDecode(string $input): ?array
    {
        // 1. Remove blocos de código Markdown (```json ... ```)
        $clean = preg_replace('/```(?:json)?/i', '', $input);
        
        // 2. Tenta encontrar o primeiro { e o último }
        if (preg_match('/\{.*\}/s', $clean, $matches)) {
            $clean = $matches[0];
        }

        // 3. Tenta decodificar direto
        $decoded = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // 4. TENTATIVA DE REPARO (Se falhou)
        // Remove vírgulas traidoras no final de objetos (ex: "a": 1, })
        $clean = preg_replace('/,\s*}/', '}', $clean);
        $clean = preg_replace('/,\s*]/', ']', $clean);
        
        // Tenta decodificar de novo
        $decoded = json_decode($clean, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log do erro de sintaxe para debug
            Log::warning("Ollama JSON Syntax Error: " . json_last_error_msg());
        }

        return $decoded;
    }

    private function mapPortugueseKeysToEnglish(array $ptData): array
    {
        // Limpa números (aceita 2,5 e 2.5) e remove letras (ex: "25g" vira 25)
        $cleanNum = function($key) use ($ptData) {
            if (!isset($ptData[$key])) return null;
            $val = $ptData[$key];
            if (is_numeric($val)) return (float) $val;
            
            // Remove tudo que não for numero, ponto ou virgula
            $val = preg_replace('/[^0-9,\.-]/', '', (string)$val);
            $val = str_replace(',', '.', $val);
            return (float) $val;
        };

        // Extração inteligente da unidade/peso
        $servingInfo = $ptData['tamanho_porcao'] ?? '';
        $servingWeight = null;
        
        // Regex para pegar o primeiro número seguido de g/ml/kg
        if (preg_match('/(\d+[,.]?\d*)\s*(g|ml|kg|l)/i', $servingInfo, $m)) {
            $servingWeight = (float) str_replace(',', '.', $m[1]);
        }

        return [
            'serving_per_container' => $ptData['porcoes_embalagem'] ?? null,
            'serving_info'          => $servingInfo,
            'serving_weight'        => $servingWeight,
            'serving_size_unit'     => $servingInfo, // Mantém o texto original para referência
            'serving_size_quantity' => $servingWeight, // Assume a quantidade como o peso principal

            'calories'          => $cleanNum('calorias'),
            'total_carb'        => $cleanNum('carboidratos'),
            'total_carb_dv'     => $cleanNum('vd_carboidratos'),
            'total_sugars'      => $cleanNum('acucares_totais'),
            'added_sugars'      => $cleanNum('acucares_adicionados'),
            'added_sugars_dv'   => null,
            'protein'           => $cleanNum('proteinas'),
            'protein_dv'        => $cleanNum('vd_proteinas'),
            'total_fat'         => $cleanNum('gorduras_totais'),
            'total_fat_dv'      => $cleanNum('vd_gorduras_totais'),
            'sat_fat'           => $cleanNum('gorduras_saturadas'),
            'sat_fat_dv'        => $cleanNum('vd_gorduras_saturadas'),
            'trans_fat'         => $cleanNum('gorduras_trans'),
            'trans_fat_dv'      => null,
            'fiber'             => $cleanNum('fibra'),
            'fiber_dv'          => $cleanNum('vd_fibra'),
            'sodium'            => $cleanNum('sodio'),
            'sodium_dv'         => $cleanNum('vd_sodio'),
            
            'calcium'           => $cleanNum('calcio'),
            'iron'              => $cleanNum('ferro'),
            'potassium'         => $cleanNum('potassio'),
        ];
    }

    private function query(string $model, string $prompt, ?string $image, bool $json, int $timeout)
    {
        try {
            $payload = [
                'model' => $model,
                'stream' => false,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'options' => [
                    'temperature' => 0.0, // Força determinismo máximo
                    'num_ctx' => 4096
                ]
            ];

            if ($image) $payload['messages'][0]['images'] = [$image];
            if ($json) $payload['format'] = 'json';

            $response = Http::timeout($timeout)->connectTimeout(5)->post("{$this->host}/api/chat", $payload);

            if ($response->successful()) {
                return $response->json('message.content');
            }

            Log::error("Ollama Error: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Ollama Exception: " . $e->getMessage());
            return null;
        }
    }
}