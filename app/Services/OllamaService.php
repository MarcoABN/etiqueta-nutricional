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
     * MOTOR DE VISÃO: Focado em LEITURA (OCR) dos termos em Português.
     */
    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 180): ?array
    {
        $prompt = <<<EOT
Analise esta imagem de TABELA NUTRICIONAL. Encontre os números correspondentes aos termos em Português abaixo.

ATENÇÃO:
1. "Porções por embalagem" geralmente está no topo, fora da grade principal.
2. "Cálcio", "Ferro" e Vitaminas geralmente estão no rodapé da tabela.
3. Se o valor for "Zero", "0", "Não contém" ou insignificante, retorne 0.

Preencha este JSON mapeando o texto da imagem (PT) para as chaves (EN):

- Texto na Imagem: "Porções por embalagem" ou "Contém X unidades" -> chave: serving_per_container
- Texto na Imagem: "Porção", "Porção de" -> chave: serving_info (ex: "25g (3 biscoitos)")
- Texto na Imagem: "Valor Energético" ou "Calorias" -> chave: calories
- Texto na Imagem: "Carboidratos" -> chave: total_carb
- Texto na Imagem: "Açúcares totais" -> chave: total_sugars
- Texto na Imagem: "Açúcares adicionados" -> chave: added_sugars
- Texto na Imagem: "Proteínas" -> chave: protein
- Texto na Imagem: "Gorduras Totais" -> chave: total_fat
- Texto na Imagem: "Gorduras Saturadas" -> chave: sat_fat
- Texto na Imagem: "Gorduras Trans" -> chave: trans_fat
- Texto na Imagem: "Fibra Alimentar" -> chave: fiber
- Texto na Imagem: "Sódio" -> chave: sodium
- Texto na Imagem: "Cálcio" -> chave: calcium
- Texto na Imagem: "Ferro" -> chave: iron
- Texto na Imagem: "Potássio" -> chave: potassium

Extraia também %VD se disponível (sufixo _dv).

JSON KEYS ESPERADAS:
{
  "serving_per_container": "string",
  "serving_weight": numero (apenas o peso em gramas da porção),
  "serving_size_quantity": "string/numero",
  "serving_size_unit": "string (traduza: 'xícara'->'cup', 'unidade'->'piece')",
  "calories": numero,
  "total_carb": numero,
  "total_carb_dv": numero,
  "total_sugars": numero,
  "added_sugars": numero,
  "added_sugars_dv": numero,
  "protein": numero,
  "protein_dv": numero,
  "total_fat": numero,
  "total_fat_dv": numero,
  "sat_fat": numero,
  "sat_fat_dv": numero,
  "trans_fat": numero,
  "trans_fat_dv": numero,
  "fiber": numero,
  "fiber_dv": numero,
  "sodium": numero,
  "sodium_dv": numero,
  "calcium": number,
  "iron": number,
  "potassium": number
}

Retorne APENAS o JSON.
EOT;

        return $this->query($this->visionModel, $prompt, $base64Image, true, $timeoutSeconds);
    }

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