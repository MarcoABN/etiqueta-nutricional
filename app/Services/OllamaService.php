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
        // Garante que o IP correto seja usado. Ajuste se necessário no .env
        $this->host = rtrim(env('OLLAMA_HOST', 'http://100.89.133.69:11434'), '/');
        
        // Modelos definidos no .env ou defaults
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:8b'); 
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest');
    }

    /**
     * Extrai dados nutricionais (OCR) da imagem
     */
    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 300): ?array
    {
        // Prompt ajustado para capturar PORÇÃO e %VD
        $prompt = <<<EOT
Analise esta imagem da Tabela Nutricional.
Sua missão é extrair dados para popular um banco SQL, focando na coluna "PORÇÃO" e na coluna "%VD".

REGRAS:
1. Extraia o valor da PORÇÃO (ex: "10g") e o %VD (ex: "5") correspondente.
2. Ignore a coluna "100g". Foque na porção definida no topo.
3. Se o %VD não existir ou for zero, retorne 0 ou null.
4. Extraia Ingredientes e Alérgicos se visíveis.

Retorne APENAS JSON estrito (sem markdown):
{
  "tamanho_porcao": "texto (ex: 30g)",
  "porcoes_embalagem": "texto (ex: aprox. 5)",
  
  "calorias": 0,
  "carboidratos": 0.0,
  "carboidratos_vd": 0,
  "acucares_totais": 0.0,
  "acucares_adicionados": 0.0,
  "acucares_adicionados_vd": 0,
  "poliois": 0.0,
  "proteinas": 0.0,
  "proteinas_vd": 0,
  "gorduras_totais": 0.0,
  "gorduras_totais_vd": 0,
  "gorduras_saturadas": 0.0,
  "gorduras_saturadas_vd": 0,
  "gorduras_trans": 0.0,
  "gorduras_trans_vd": 0,
  "gorduras_mono": 0.0,
  "gorduras_poli": 0.0,
  "fibra": 0.0,
  "fibra_vd": 0,
  "sodio": 0.0,
  "sodio_vd": 0,
  "colesterol": 0.0,
  "colesterol_vd": 0,

  "calcio": 0.0,
  "ferro": 0.0,
  "potassio": 0.0,
  "vitamina_d": 0.0,
  "vitamina_a": 0.0,
  "vitamina_c": 0.0
}
EOT;

        // Chama o método queryVision (AGORA INCLUÍDO NA CLASSE)
        $rawResponse = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$rawResponse) {
            return null;
        }

        $jsonData = $this->robustJsonDecode($rawResponse);
        
        if (!$jsonData) {
            Log::error("Ollama Vision: JSON Falhou. Raw: " . substr($rawResponse, 0, 200));
            return null;
        }

        return $this->mapPortugueseKeysToSchema($jsonData);
    }

    /**
     * Método para completação de texto simples (usado na tradução)
     */
    public function completion(string $prompt, int $timeoutSeconds = 60): ?string
    {
        // Reutiliza lógica similar, mas sem imagem e forçando modo texto se necessário
        try {
            $payload = [
                'model' => $this->textModel,
                'stream' => false,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'options' => ['temperature' => 0.0, 'num_ctx' => 4096]
            ];

            $response = Http::timeout($timeoutSeconds)->post("{$this->host}/api/chat", $payload);

            if ($response->successful()) {
                return $response->json('message.content');
            }
            return null;
        } catch (\Exception $e) {
            Log::error("Ollama Text Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * O MÉTODO QUE FALTAVA: queryVision
     * Executa a chamada HTTP específica para visão com timeout ajustável
     */
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
                'format' => 'json', // Força JSON
                'options' => [
                    'temperature' => 0.1, 
                    'num_ctx' => 2048
                ]
            ];

            $response = Http::timeout($timeout)
                ->connectTimeout(5)
                ->post("{$this->host}/api/chat", $payload);

            if ($response->successful()) {
                return $response->json('message.content');
            }
            
            Log::error("Ollama HTTP Error: {$response->status()} - {$response->body()}");
            return null;

        } catch (\Exception $e) {
            Log::error("Ollama Connection Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Limpa e decodifica o JSON retornado pela IA
     */
    private function robustJsonDecode(string $input): ?array
    {
        $clean = preg_replace('/```(?:json)?/i', '', $input);
        
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $clean = $matches[0];
        }

        $clean = preg_replace('/,\s*}/', '}', $clean);
        $clean = preg_replace('/,\s*]/', ']', $clean);

        return json_decode($clean, true);
    }

    /**
     * Mapeia o JSON em Português para as colunas do Banco (Inglês)
     */
    private function mapPortugueseKeysToSchema(array $ptData): array
    {
        // Helper para limpar números (valores absolutos)
        $cleanNum = function($key) use ($ptData) {
            if (!isset($ptData[$key]) || $ptData[$key] === null) return null;
            $val = preg_replace('/[^0-9,\.-]/', '', (string)$ptData[$key]);
            $val = str_replace(',', '.', $val);
            return is_numeric($val) ? (string)$val : null;
        };

        // Helper específico para %VD
        $cleanDV = function($key) use ($ptData) {
            if (!isset($ptData[$key])) return null;
            $val = preg_replace('/[^0-9,\.-]/', '', (string)$ptData[$key]);
            $val = str_replace(',', '.', $val);
            return ($val === '' || $val === null) ? null : (string)$val;
        };

        $cleanText = fn($key) => isset($ptData[$key]) ? trim((string)$ptData[$key]) : null;

        return [
            // Identificação
            'serving_weight'        => $cleanText('tamanho_porcao'),
            'servings_per_container'=> $cleanText('porcoes_embalagem'),

            // Calorias
            'calories'          => $cleanNum('calorias'),

            // Carboidratos
            'total_carb'        => $cleanNum('carboidratos'),
            'total_carb_dv'     => $cleanDV('carboidratos_vd'),

            // Açúcares
            'total_sugars'      => $cleanNum('acucares_totais'),
            'added_sugars'      => $cleanNum('acucares_adicionados'),
            'added_sugars_dv'   => $cleanDV('acucares_adicionados_vd'),
            'sugar_alcohol'     => $cleanNum('poliois'),

            // Proteínas
            'protein'           => $cleanNum('proteinas'),
            'protein_dv'        => $cleanDV('proteinas_vd'),

            // Gorduras
            'total_fat'         => $cleanNum('gorduras_totais'),
            'total_fat_dv'      => $cleanDV('gorduras_totais_vd'),

            'sat_fat'           => $cleanNum('gorduras_saturadas'),
            'sat_fat_dv'        => $cleanDV('gorduras_saturadas_vd'),

            'trans_fat'         => $cleanNum('gorduras_trans'),
            'trans_fat_dv'      => $cleanDV('gorduras_trans_vd'),

            'mono_fat'          => $cleanNum('gorduras_mono'),
            'poly_fat'          => $cleanNum('gorduras_poli'),

            // Fibras e Sódio
            'fiber'             => $cleanNum('fibra'),
            'fiber_dv'          => $cleanDV('fibra_vd'),

            'sodium'            => $cleanNum('sodio'),
            'sodium_dv'         => $cleanDV('sodio_vd'),

            'cholesterol'       => $cleanNum('colesterol'),
            'cholesterol_dv'    => $cleanDV('colesterol_vd'),

            // Micros
            'calcium'           => $cleanNum('calcio'),
            'iron'              => $cleanNum('ferro'),
            'potassium'         => $cleanNum('potassio'),
            'vitamin_d'         => $cleanNum('vitamina_d'),
            'vitamin_c'         => $cleanNum('vitamina_c'),
            'vitamin_a'         => $cleanNum('vitamina_a'),



        ];
    }
}