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
        
        // MOTOR 1: VISÃO (Lento, Detalhado, para Imagens)
        // Padrão: qwen3-vl:8b
        $this->visionModel = env('OLLAMA_MODEL', 'qwen3-vl:8b');
        
        // MOTOR 2: TEXTO (Rápido, Raciocínio, para Tradução)
        // Padrão: gemma3:4b (ou llama3.2)
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma3:4b');
    }

    /**
     * MOTOR DE VISÃO: Extrai dados da tabela nutricional com regras FDA.
     * Usa o modelo pesado (Vision).
     */
    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 180): ?array
    {
        // Recuperamos o prompt rico com regras de conversão de unidades
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

### JSON OUTPUT KEYS (Estrito):
- serving_per_container (texto)
- serving_weight (numero, ex: 25)
- serving_size_quantity (numero/fracao, ex: "2 1/2")
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

Retorne APENAS o JSON. Se ilegível, use null.
EOT;

        // Chama o método query usando explicitamente o visionModel
        return $this->query($this->visionModel, $prompt, $base64Image, true, $timeoutSeconds);
    }

    /**
     * MOTOR DE TEXTO: Tradução e lógica de strings.
     * Usa o modelo leve (Text).
     */
    public function completion(string $prompt, int $timeoutSeconds = 30): ?string
    {
        // Chama o método query usando explicitamente o textModel
        // Sem imagem, apenas texto
        return $this->query($this->textModel, $prompt, null, false, $timeoutSeconds);
    }

    /**
     * Executor Genérico de Requisições Ollama
     */
    private function query(string $model, string $prompt, ?string $image, bool $json, int $timeout)
    {
        try {
            $payload = [
                'model' => $model, // Modelo dinâmico (Visão ou Texto)
                'stream' => false,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ]
                ],
                'options' => [
                    'temperature' => 0.1, // Baixa criatividade para precisão
                    'num_ctx' => 4096     // Contexto alto para tabelas grandes
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
                    // Limpeza de segurança para JSON (caso a IA mande Markdown)
                    $clean = str_replace(['```json', '```'], '', $content);
                    
                    // Regex para pegar apenas o objeto JSON {}
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