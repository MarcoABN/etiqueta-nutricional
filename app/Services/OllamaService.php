<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
//backup de versão - momento estável
class OllamaService
{
    protected string $host;
    protected string $visionModel;
    protected string $textModel;

    public function __construct()
    {
        $this->host = rtrim(env('OLLAMA_HOST', 'http://127.0.0.1:11434'), '/');
        
        // MOTOR 1: VISÃO (Lento, Detalhado, para Imagens)
        $this->visionModel = env('OLLAMA_MODEL', 'qwen3-vl:8b');
        
        // MOTOR 2: TEXTO (Rápido, Raciocínio, para Tradução)
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma3:4b');
    }

    /**
     * MOTOR DE VISÃO: Extrai dados da tabela nutricional com regras FDA.
     */
    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 180): ?array
    {
        $prompt = <<<EOT
Analise a tabela nutricional na imagem. Extraia os dados para JSON.

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
- serving_per_container (texto)
- serving_weight (numero)
- serving_size_quantity (numero/fracao)
- serving_size_unit (TRADUZIDO)
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

Retorne APENAS o JSON. Se ilegível, use null.
EOT;

        return $this->query($this->visionModel, $prompt, $base64Image, true, $timeoutSeconds);
    }

    /**
     * MOTOR DE TEXTO: Tradução rápida.
     */
    public function completion(string $prompt, int $timeoutSeconds = 30): ?string
    {
        return $this->query($this->textModel, $prompt, null, false, $timeoutSeconds);
    }

    private function query(string $model, string $prompt, ?string $image, bool $json, int $timeout)
    {
        try {
            $payload = [
                'model' => $model,
                'stream' => false,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ]
                ],
                'options' => [
                    'temperature' => 0.1,
                    'num_ctx' => 4096
                ]
            ];

            if ($image) {
                $payload['messages'][0]['images'] = [$image];
            }

            if ($json) {
                $payload['format'] = 'json';
            }

            $response = Http::timeout($timeout)
                ->connectTimeout(5)
                ->post("{$this->host}/api/chat", $payload);

            if ($response->successful()) {
                $content = $response->json('message.content');
                
                if ($json) {
                    $clean = str_replace(['```json', '```'], '', $content);
                    if (preg_match('/\{.*\}/s', $clean, $matches)) {
                        $clean = $matches[0];
                    }
                    return json_decode($clean, true);
                }
                return $content;
            }

            Log::error("Ollama Error [{$model}]: " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error("Ollama Exception [{$model}]: " . $e->getMessage());
            return null;
        }
    }
}