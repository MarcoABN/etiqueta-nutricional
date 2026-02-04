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
        $this->host = rtrim(env('OLLAMA_HOST', 'http://127.0.0.1:11434'), '/');
        // Garante que usa o modelo exato da sua print (qwen3-vl:8b)
        $this->model = env('OLLAMA_MODEL', 'qwen3-vl:8b'); 
    }

    // Adicionamos o parâmetro $timeoutSeconds
    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 300): ?array
    {
        $prompt = <<<EOT
Analise a tabela nutricional na imagem. Extraia os dados para um JSON estrito.
Regras:
1. Retorne APENAS o JSON.
2. Se ilegível, use null.
3. Remova unidades (g, mg, kcal).
4. Chaves obrigatórias: servings_per_container, serving_weight, serving_size_quantity, serving_size_unit, calories, total_carb, total_carb_dv, total_sugars, added_sugars, added_sugars_dv, protein, protein_dv, total_fat, total_fat_dv, sat_fat, sat_fat_dv, trans_fat, trans_fat_dv, fiber, fiber_dv, sodium, sodium_dv.
EOT;

        return $this->query($prompt, $base64Image, true, $timeoutSeconds);
    }

    public function completion(string $prompt, int $timeoutSeconds = 10): ?string
    {
        // Timeout curto por padrão para texto
        return $this->query($prompt, null, false, $timeoutSeconds);
    }

    private function query(string $prompt, ?string $image, bool $json, int $timeout)
    {
        try {
            $payload = [
                'model' => $this->model,
                'stream' => false,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ]
                ],
                'options' => [
                    'temperature' => 0.1,
                ]
            ];

            if ($image) {
                $payload['messages'][0]['images'] = [$image];
            }

            if ($json) {
                $payload['format'] = 'json';
            }

            // AQUI ESTÁ A CORREÇÃO PRINCIPAL: O timeout explícito
            $response = Http::timeout($timeout)
                ->connectTimeout(10) // Timeout de conexão curto
                ->post("{$this->host}/api/chat", $payload);

            if ($response->successful()) {
                $content = $response->json('message.content');
                if ($json) {
                    $clean = str_replace(['```json', '```'], '', $content);
                    return json_decode($clean, true);
                }
                return $content;
            }

            // Log detalhado do erro (Isso vai aparecer no laravel.log)
            Log::error("Ollama API Error [{$response->status()}]: " . $response->body());
            return null;

        } catch (\Exception $e) {
            // Log da exceção (Timeout, DNS, etc)
            Log::error("Ollama Connection Exception: " . $e->getMessage());
            return null;
        }
    }
}