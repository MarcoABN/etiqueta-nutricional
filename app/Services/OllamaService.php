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
     * MOTOR DE VISÃO: Prompt Enriquecido para Rótulos Brasileiros (PT-BR -> EN)
     */
    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 180): ?array
    {
        $prompt = <<<EOT
You are a Data Extraction Engine reading a Brazilian Nutrition Facts label (Tabela Nutricional).
Extract all visible data into a strict JSON format.

### 1. TRANSLATION MAPPING (PT-BR -> JSON KEY):
Look for these Portuguese terms and map them to the English keys:
- "Porções por embalagem", "Contém X porções" -> serving_per_container (Search HEADER/FOOTER outside the grid)
- "Porção", "Porção de" -> serving_info
- "Valor energético", "Calorias" -> calories
- "Carboidratos" -> total_carb
- "Açúcares totais" -> total_sugars
- "Açúcares adicionados" -> added_sugars
- "Proteínas" -> protein
- "Gorduras totais" -> total_fat
- "Gorduras saturadas" -> sat_fat
- "Gorduras trans" -> trans_fat
- "Fibra alimentar" -> fiber
- "Sódio" -> sodium
- "Cálcio" -> calcium
- "Ferro" -> iron
- "Potássio" -> potassium
- "Vitamina D" -> vitamin_d
- "Vitamina C" -> vitamin_c
- "Vitamina A" -> vitamin_a

### 2. UNIT STANDARDIZATION:
Translate 'serving_size_unit' to English:
- "Xícara" -> "cup"
- "Colher de sopa" -> "tbsp"
- "Colher de chá" -> "tsp"
- "Unidade", "Biscoito" -> "piece"
- "Fatia" -> "slice"
- "Copo" -> "glass"

### 3. JSON OUTPUT KEYS (Fill with NULL if not visible):
{
  "serving_per_container": "string (e.g., 'aprox. 8')",
  "serving_weight": number (e.g., 25),
  "serving_size_quantity": "string/number",
  "serving_size_unit": "string",
  "calories": number,
  "total_carb": number,
  "total_carb_dv": number,
  "total_sugars": number,
  "added_sugars": number,
  "added_sugars_dv": number,
  "protein": number,
  "protein_dv": number,
  "total_fat": number,
  "total_fat_dv": number,
  "sat_fat": number,
  "sat_fat_dv": number,
  "trans_fat": number,
  "trans_fat_dv": number,
  "fiber": number,
  "fiber_dv": number,
  "sodium": number,
  "sodium_dv": number,
  "calcium": number,
  "iron": number,
  "potassium": number,
  "vitamin_d": number,
  "vitamin_c": number,
  "vitamin_a": number
}

Return ONLY the JSON.
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
                    'temperature' => 0.1, // Baixa criatividade
                    'num_ctx' => 4096     // Contexto alto para ler a tabela toda
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