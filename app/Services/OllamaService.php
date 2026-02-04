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
     * MOTOR DE VISÃO: Focado APENAS em ler (OCR).
     * Não pedimos para traduzir chaves, apenas valores e unidades.
     */
    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 180): ?array
    {
        $prompt = <<<EOT
Analise a imagem da Tabela Nutricional.
Sua tarefa é extrair os números exatos.

Preencha este JSON usando as chaves em Português conforme aparecem no rótulo:
{
  "porcoes_embalagem": "texto (ex: aprox. 8)",
  "tamanho_porcao": "texto (ex: 25g (3 biscoitos))",
  "calorias": numero,
  "carboidratos": numero,
  "acucares_totais": numero,
  "acucares_adicionados": numero,
  "proteinas": numero,
  "gorduras_totais": numero,
  "gorduras_saturadas": numero,
  "gorduras_trans": numero,
  "fibra": numero,
  "sodio": numero,
  "calcio": numero,
  "ferro": numero,
  "potassio": numero,
  "vd_carboidratos": numero,
  "vd_proteinas": numero,
  "vd_gorduras_totais": numero,
  "vd_gorduras_saturadas": numero,
  "vd_fibra": numero,
  "vd_sodio": numero
}

Se um campo não existir na imagem, use null.
Retorne APENAS o JSON.
EOT;

        // 1. Pega os dados crus em chaves PT-BR
        $rawData = $this->query($this->visionModel, $prompt, $base64Image, true, $timeoutSeconds);

        if (!$rawData) {
            Log::error("Ollama: Retornou vazio na extração visual.");
            return null;
        }

        // 2. O PHP faz o mapeamento seguro (PT -> EN)
        return $this->mapPortugueseKeysToEnglish($rawData);
    }

    /**
     * MOTOR DE TEXTO
     */
    public function completion(string $prompt, int $timeoutSeconds = 30): ?string
    {
        return $this->query($this->textModel, $prompt, null, false, $timeoutSeconds);
    }

    /**
     * Mapeia o JSON "sujo" em PT-BR para as colunas do Banco (EN)
     */
    private function mapPortugueseKeysToEnglish(array $ptData): array
    {
        // Função auxiliar para limpar números (trocar vírgula por ponto)
        $cleanNum = fn($k) => isset($ptData[$k]) 
            ? (float) str_replace(',', '.', (string)$ptData[$k]) 
            : null;

        // Tenta extrair peso e medida da string de porção
        // Ex: "25g (1 xícara)"
        $servingWeight = null;
        $servingQty = null;
        $servingUnit = null;
        
        if (!empty($ptData['tamanho_porcao'])) {
            // Regex simples para tentar pegar o peso em gramas
            if (preg_match('/(\d+)[,\.]?\d*\s*g/i', $ptData['tamanho_porcao'], $m)) {
                $servingWeight = $m[1];
            }
            // A unidade deixamos como string bruta para não complicar agora
            $servingUnit = $ptData['tamanho_porcao']; 
        }

        return [
            // Identificação
            'serving_per_container' => $ptData['porcoes_embalagem'] ?? null,
            'serving_info'          => $ptData['tamanho_porcao'] ?? null,
            'serving_weight'        => $servingWeight, 
            'serving_size_unit'     => $servingUnit, // Jogamos o texto todo aqui por segurança
            
            // Macros
            'calories'          => $cleanNum('calorias'),
            'total_carb'        => $cleanNum('carboidratos'),
            'total_carb_dv'     => $cleanNum('vd_carboidratos'),
            'total_sugars'      => $cleanNum('acucares_totais'),
            'added_sugars'      => $cleanNum('acucares_adicionados'),
            'added_sugars_dv'   => null, // Geralmente IA falha nesse específico
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
            
            // Micros
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
                'options' => ['temperature' => 0.1, 'num_ctx' => 4096]
            ];

            if ($image) $payload['messages'][0]['images'] = [$image];
            if ($json) $payload['format'] = 'json';

            $response = Http::timeout($timeout)->connectTimeout(5)->post("{$this->host}/api/chat", $payload);

            if ($response->successful()) {
                $content = $response->json('message.content');
                
                // LOG DE DEBUG IMPORTANTE: Veja no laravel.log o que a IA retornou
                Log::info("RAW OLLAMA ($model): " . substr($content, 0, 500) . "..."); 

                if ($json) {
                    $clean = str_replace(['```json', '```'], '', $content);
                    if (preg_match('/\{.*\}/s', $clean, $matches)) $clean = $matches[0];
                    return json_decode($clean, true);
                }
                return $content;
            }

            Log::error("Ollama Error: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Ollama Exception: " . $e->getMessage());
            return null;
        }
    }
}