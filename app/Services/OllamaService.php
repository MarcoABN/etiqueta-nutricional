<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $model;

    public function __construct()
    {
        // Pega do .env (Use o IP do Tailscale)
        $this->host = rtrim(env('OLLAMA_HOST', 'http://127.0.0.1:11434'), '/');
        // Modelo definido na imagem que você mandou (qwen3:8b ou qwen3-vl:8b se tiver suporte a visão)
        // RECOMENDAÇÃO: Use qwen2.5-vl ou qwen3-vl para imagens.
        $this->model = env('OLLAMA_MODEL', 'qwen2.5-vl');
    }

    public function extractNutritionalData(string $base64Image): ?array
    {
        // Mapeamento exato para suas colunas do banco
        $prompt = <<<EOT
Analise a tabela nutricional na imagem. Extraia os dados para um JSON estrito.
Regras:
1. Retorne APENAS o JSON. Sem markdown, sem explicações.
2. Se um valor não existir ou for ilegível, use null.
3. Remova unidades (g, mg, kcal, %) dos valores numéricos, deixe apenas o número.
4. Mapeie exatamente para estas chaves JSON:

- servings_per_container (texto aprox)
- serving_weight (ex: "25g")
- serving_size_quantity (ex: "2 1/2")
- serving_size_unit (ex: "xícaras")

- calories (numero)
- total_carb (numero)
- total_carb_dv (numero, %VD)
- total_sugars (numero)
- added_sugars (numero)
- added_sugars_dv (numero, %VD)
- protein (numero)
- protein_dv (numero, %VD)
- total_fat (numero)
- total_fat_dv (numero, %VD)
- sat_fat (numero)
- sat_fat_dv (numero, %VD)
- trans_fat (numero)
- trans_fat_dv (numero, %VD)
- fiber (numero)
- fiber_dv (numero, %VD)
- sodium (numero)
- sodium_dv (numero, %VD)

EOT;

        try {
            // Timeout generoso (3 min) para fila sequencial
            $response = Http::timeout(180)->post("{$this->host}/api/chat", [
                'model' => $this->model,
                'format' => 'json',
                'stream' => false,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                        'images' => [$base64Image]
                    ]
                ],
                'options' => [
                    'temperature' => 0.0, // Zero criatividade = máxima precisão
                ]
            ]);

            if ($response->successful()) {
                $content = $response->json('message.content');
                // Limpeza extra caso o modelo mande ```json ... ```
                $cleanContent = str_replace(['```json', '```'], '', $content);
                return json_decode($cleanContent, true);
            }

            Log::error('Ollama Error: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('Ollama Connection Error: ' . $e->getMessage());
            return null;
        }
    }

    public function completion(string $prompt): ?string
    {
        try {
            $response = Http::timeout(60)->post("{$this->host}/api/chat", [
                'model' => $this->model,
                'stream' => false,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'options' => [
                    'temperature' => 0.1, // Baixa criatividade para tradução fiel
                ]
            ]);

            if ($response->successful()) {
                return $response->json('message.content');
            }

            Log::warning('Ollama Local falhou na tradução: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('Erro de conexão Ollama: ' . $e->getMessage());
            return null;
        }
    }
}