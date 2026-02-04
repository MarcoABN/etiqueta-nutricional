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
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest');
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 300): ?array
    {
        // Prompt original mantido e reforçado
        $prompt = <<<EOT
Analise esta imagem da Tabela Nutricional.
Sua missão é extrair dados para popular um banco SQL.
ATENÇÃO: Extraia EXATAMENTE os números como estão na imagem.

REGRAS OBRIGATÓRIAS:
1. Retorne APENAS um objeto JSON válido. Não use Markdown (```json).
2. Se o valor for "Zero", "0", ou não existir, retorne 0.
3. Foque na coluna "Porção" principal. Ignore a coluna "100g" ou "%VD" se não for pedido.

SCHEMA JSON ALVO:
{
  "tamanho_porcao": "string (ex: 30g)",
  "porcoes_embalagem": "string",
  "calorias": "number",
  "carboidratos": "number",
  "acucares_totais": "number",
  "acucares_adicionados": "number",
  "proteinas": "number",
  "gorduras_totais": "number",
  "gorduras_saturadas": "number",
  "gorduras_trans": "number",
  "fibra": "number",
  "sodio": "number",
  "colesterol": "number"
}
EOT;

        $response = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$response) return null;

        $jsonData = $this->robustJsonDecode($response);
        
        if (!$jsonData) {
            Log::error("Ollama Vision: Falha ao decodificar JSON. Retorno: " . substr($response, 0, 150));
            return null;
        }

        return $this->mapPortugueseKeysToSchema($jsonData);
    }

    private function queryVision(string $model, string $prompt, string $image, int $timeout): ?string
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
                    'temperature' => 0.1, // Temperatura baixa para precisão
                    'num_ctx' => 4096,    // Contexto maior para imagens detalhadas
                ]
            ];

            // Tenta forçar modo JSON se o modelo suportar (opcional, remove se der erro)
            // $payload['format'] = 'json'; 

            $response = Http::timeout($timeout)
                ->connectTimeout(10) // Timeout de conexão curto, timeout de leitura longo
                ->post("{$this->host}/api/chat", $payload);

            if ($response->successful()) {
                return $response->json('message.content');
            }
            
            Log::error("Ollama API Erro: {$response->status()} - {$response->body()}");
            return null;

        } catch (\Exception $e) {
            Log::error("Ollama Conexão Exception: " . $e->getMessage());
            return null;
        }
    }

    private function robustJsonDecode(string $input): ?array
    {
        // Limpeza agressiva de Markdown que o Ollama adora colocar
        $clean = preg_replace('/```(?:json)?/i', '', $input);
        $clean = str_replace(['```', '`'], '', $clean);
        
        // Tenta encontrar o primeiro { e o último }
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $clean = $matches[0];
        }

        // Corrige vírgulas sobrando antes de fechar chaves/colchetes
        $clean = preg_replace('/,\s*}/', '}', $clean);
        $clean = preg_replace('/,\s*]/', ']', $clean);

        return json_decode($clean, true);
    }

    private function mapPortugueseKeysToSchema(array $ptData): array
    {
        // Helper para limpar números (remove 'g', 'mg', troca vírgula por ponto)
        $cleanNum = function($key) use ($ptData) {
            if (!isset($ptData[$key])) return '0';
            $val = preg_replace('/[^0-9,\.-]/', '', (string)$ptData[$key]);
            $val = str_replace(',', '.', $val);
            return is_numeric($val) ? (string)$val : '0';
        };

        $cleanText = fn($key) => isset($ptData[$key]) ? trim((string)$ptData[$key]) : null;

        return [
            'serving_weight'        => $cleanText('tamanho_porcao'),
            'servings_per_container'=> $cleanText('porcoes_embalagem'),
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
            'cholesterol'       => $cleanNum('colesterol'),
        ];
    }
    
    // Método completion (texto) pode ser mantido igual ao original
    public function completion(string $prompt): ?string { /* ... */ return null; }
}