<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $visionModel;

    public function __construct()
    {
        $this->host = rtrim(env('OLLAMA_HOST', 'http://100.89.133.69:11434'), '/');
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:8b'); 
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        // Prompt otimizado com a ordem informada pelo usuário
        $prompt = <<<EOT
Analise esta Tabela Nutricional. Extraia os dados seguindo estritamente a ordem padrão.

ORDEM DOS DADOS NA IMAGEM (Geralmente):
1. Porção (Ex: 30g) e Medida Caseira (Ex: 1 colher).
2. Valor Energético / Calorias.
3. Carboidratos.
4. Açúcares Totais.
5. Açúcares Adicionados.
6. Proteínas.
7. Gorduras Totais.
8. Gorduras Saturadas.
9. Gorduras Trans.
10. Fibra Alimentar.
11. Sódio.

REGRAS DE EXTRAÇÃO:
- "serving_weight": Apenas o peso/volume numérico (ex: "30g", "200ml").
- "serving_size_quantity": A quantidade da medida caseira (ex: "1.5", "1").
- "serving_size_unit": O nome da medida caseira (ex: "xícara", "fatia", "unidade").
- Se o valor for "Zero", "0", ou traço "-", retorne 0.
- Retorne APENAS o JSON abaixo.

{
  "serving_weight": "string",
  "serving_size_quantity": "string",
  "serving_size_unit": "string",
  "servings_per_container": "string",
  "calories": "number",
  "total_carb": "number",
  "total_sugars": "number",
  "added_sugars": "number",
  "protein": "number",
  "total_fat": "number",
  "sat_fat": "number",
  "trans_fat": "number",
  "fiber": "number",
  "sodium": "number"
}
EOT;

        $response = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$response) return null;

        $jsonData = $this->robustJsonDecode($response);
        
        if (!$jsonData) {
            Log::error("Ollama: JSON inválido. Raw: " . substr($response, 0, 100) . "...");
            return null;
        }

        return $this->sanitizeData($jsonData);
    }

    private function queryVision(string $model, string $prompt, string $image, int $timeout): ?string
    {
        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(10) // Timeout rápido para saber se o server existe
                ->post("{$this->host}/api/chat", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt, 'images' => [$image]]
                    ],
                    'stream' => false,
                    'format' => 'json', // Força saída JSON estruturada
                    'options' => [
                        'temperature' => 0.0, // Zero criatividade, apenas leitura pura
                        'num_ctx' => 4096,
                    ]
                ]);

            if ($response->successful()) {
                return $response->json('message.content');
            }
            
            Log::error("Ollama API Erro: {$response->status()} - {$response->body()}");
            return null;

        } catch (\Exception $e) {
            Log::error("Ollama Conexão Falhou: " . $e->getMessage());
            return null;
        }
    }

    private function robustJsonDecode(string $input): ?array
    {
        $clean = preg_replace('/```(?:json)?/i', '', $input);
        $clean = str_replace(['```', '`'], '', $clean);
        
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $clean = $matches[0];
        }
        
        // Correção comum de vírgulas trailing
        $clean = preg_replace('/,\s*}/', '}', $clean);

        return json_decode($clean, true);
    }

    private function sanitizeData(array $data): array
    {
        // Garante que números sejam números e strings sejam limpas
        $cleanNum = function($key) use ($data) {
            if (!isset($data[$key])) return '0';
            // Remove tudo que não for número, ponto ou vírgula
            $val = preg_replace('/[^0-9,\.-]/', '', (string)$data[$key]);
            $val = str_replace(',', '.', $val);
            return is_numeric($val) ? (string)$val : '0';
        };

        return [
            'serving_weight'        => $data['serving_weight'] ?? null,
            'serving_size_quantity' => $data['serving_size_quantity'] ?? null,
            'serving_size_unit'     => $data['serving_size_unit'] ?? null,
            'servings_per_container'=> $data['servings_per_container'] ?? null,
            
            'calories'      => $cleanNum('calories'),
            'total_carb'    => $cleanNum('total_carb'),
            'total_sugars'  => $cleanNum('total_sugars'),
            'added_sugars'  => $cleanNum('added_sugars'),
            'protein'       => $cleanNum('protein'),
            'total_fat'     => $cleanNum('total_fat'),
            'sat_fat'       => $cleanNum('sat_fat'),
            'trans_fat'     => $cleanNum('trans_fat'),
            'fiber'         => $cleanNum('fiber'),
            'sodium'        => $cleanNum('sodium'),
        ];
    }
}