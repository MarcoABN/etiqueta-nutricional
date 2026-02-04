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
        $this->model = env('OLLAMA_MODEL', 'qwen3-vl:8b'); 
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 300): ?array
    {
        // Prompt refinado com Tabela de Conversão de Unidades (FDA)
        $prompt = <<<EOT
Analise a tabela nutricional. Extraia os dados para JSON.

### REGRAS DE UNIDADE (FDA HOUSEHOLD MEASURES):
Traduza o campo 'serving_size_unit' do Português para Inglês Padrão:
- "Xícara", "Xic" -> "cup"
- "Colher de sopa" -> "tbsp"
- "Colher de chá" -> "tsp"
- "Unidade", "Biscoito" -> "piece"
- "Fatia" -> "slice"
- "Copo" -> "glass"
- "Pedaço" -> "piece"
- "Porção" -> "serving"

### JSON OUTPUT KEYS:
- serving_per_container (texto aprox)
- serving_weight (apenas numero, ex: 25)
- serving_size_quantity (apenas numero/fração, ex: "2 1/2")
- serving_size_unit (TRADUZIDO, ex: "cup")

- calories (numero)
- total_carb (numero)
- total_carb_dv (numero)
- total_sugars (numero)
- added_sugars (numero)
- added_sugars_dv (numero)
- protein (numero)
- protein_dv (numero)
- total_fat (numero)
- total_fat_dv (numero)
- sat_fat (numero)
- sat_fat_dv (numero)
- trans_fat (numero)
- trans_fat_dv (numero)
- fiber (numero)
- fiber_dv (numero)
- sodium (numero)
- sodium_dv (numero)

Retorne APENAS o JSON. Use null se vazio.
EOT;

        return $this->query($prompt, $base64Image, true, $timeoutSeconds);
    }

    public function completion(string $prompt, int $timeoutSeconds = 60): ?string
    {
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
                    'temperature' => 0.0, // Zero criatividade para dados precisos
                ]
            ];

            if ($image) {
                $payload['messages'][0]['images'] = [$image];
            }

            if ($json) {
                $payload['format'] = 'json';
            }

            $response = Http::timeout($timeout)
                ->connectTimeout(10)
                ->post("{$this->host}/api/chat", $payload);

            if ($response->successful()) {
                $content = $response->json('message.content');
                if ($json) {
                    // Limpeza de segurança para JSON
                    $clean = str_replace(['```json', '```'], '', $content);
                    // Remove texto antes/depois do JSON
                    $start = strpos($clean, '{');
                    $end = strrpos($clean, '}');
                    if ($start !== false && $end !== false) {
                        $clean = substr($clean, $start, $end - $start + 1);
                    }
                    return json_decode($clean, true);
                }
                return $content;
            }

            Log::error("Ollama API Error: " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error("Ollama Connection Exception: " . $e->getMessage());
            return null;
        }
    }
}