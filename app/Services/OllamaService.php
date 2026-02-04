<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $visionModel;
    protected string $textModel;

    // Dicionário de Medidas (Mantido)
    protected array $unitMap = [
        '/colher.*sopa/i' => 'Tablespoon', '/colher.*ch[aá]/i' => 'Teaspoon',
        '/x[ií]cara/i' => 'Cup', '/copo/i' => 'Cup', '/unidade/i' => 'Piece',
        '/fatia/i' => 'Slice', '/pote/i' => 'Container', '/garrafa/i' => 'Bottle',
        '/lata/i' => 'Can', '/biscoito/i' => 'Cookie', '/barra/i' => 'Bar',
        '/embalagem/i' => 'Package',
    ];

    public function __construct()
    {
        $this->host = rtrim(env('OLLAMA_HOST', 'http://100.89.133.69:11434'), '/');
        // Qwen 30B é excelente para seguir listas de texto
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:30b'); 
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest');
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        // PROMPT TEXTO PURO: Mais robusto que JSON.
        // A IA apenas lista os valores linha por linha.
        $prompt = <<<EOT
Analise a Tabela Nutricional. NÃO USE JSON. NÃO USE MARKDOWN.
Responda APENAS com as linhas abaixo, preenchendo os dados encontrados.

CABEÇALHO:
H_PORCOES: <Copie o texto da linha 'Porções por embalagem'>
H_MEDIDA: <Copie o texto da linha 'Porção' com peso e medida>

NUTRIENTES (Escreva TODOS os números encontrados na linha, da esquerda para a direita):
N_CALORIAS: <numeros>
N_CARBO: <numeros>
N_ACUCAR_TOTAL: <numeros>
N_ACUCAR_ADD: <numeros>
N_PROTEINA: <numeros>
N_GORDURA_TOTAL: <numeros>
N_GORDURA_SAT: <numeros>
N_GORDURA_TRANS: <numeros>
N_FIBRA: <numeros>
N_SODIO: <numeros>
N_COLESTEROL: <numeros>
N_CALCIO: <numeros>
N_FERRO: <numeros>
N_POTASSIO: <numeros>
N_VIT_A: <numeros>
N_VIT_C: <numeros>
N_VIT_D: <numeros>

Exemplo de resposta válida:
H_PORCOES: Cerca de 6
H_MEDIDA: 30g (2 biscoitos)
N_CALORIAS: 140 450 7
N_CARBO: 20 60 4
EOT;

        $response = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$response) return null;

        // Log para Debug: Veja exatamente o que a IA retornou
        Log::info("Raw Ollama Response:\n" . substr($response, 0, 500));

        return $this->parseTextResponse($response);
    }

    /**
     * Parser Manual de Texto (Infalível contra erros de sintaxe JSON)
     */
    private function parseTextResponse(string $text): array
    {
        $lines = explode("\n", $text);
        $data = [];

        // Mapeamento Chave IA -> Chave Banco
        $keyMap = [
            'N_CALORIAS' => 'calories',
            'N_CARBO' => 'total_carb',
            'N_ACUCAR_TOTAL' => 'total_sugars',
            'N_ACUCAR_ADD' => 'added_sugars',
            'N_PROTEINA' => 'protein',
            'N_GORDURA_TOTAL' => 'total_fat',
            'N_GORDURA_SAT' => 'sat_fat',
            'N_GORDURA_TRANS' => 'trans_fat',
            'N_FIBRA' => 'fiber',
            'N_SODIO' => 'sodium',
            'N_COLESTEROL' => 'cholesterol',
            'N_CALCIO' => 'calcium',
            'N_FERRO' => 'iron',
            'N_POTASSIO' => 'potassium',
            'N_VIT_A' => 'vitamin_a',
            'N_VIT_C' => 'vitamin_c',
            'N_VIT_D' => 'vitamin_d',
        ];

        // 1. Extração Inicial
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'H_PORCOES:')) {
                $data['header_servings'] = trim(substr($line, 10));
            } elseif (str_starts_with($line, 'H_MEDIDA:')) {
                $data['header_measure'] = trim(substr($line, 9));
            } else {
                foreach ($keyMap as $prefix => $dbKey) {
                    if (str_starts_with($line, $prefix . ':')) {
                        // Extrai todos os números da linha (ex: "140 450 7")
                        $rawNums = substr($line, strlen($prefix) + 1);
                        // Regex para pegar números inteiros ou decimais (com ponto ou vírgula)
                        preg_match_all('/[0-9]+([.,][0-9]+)?/', $rawNums, $matches);
                        
                        $nums = array_map(function($n) {
                            return (float) str_replace(',', '.', $n);
                        }, $matches[0] ?? []);
                        
                        $data['rows'][$dbKey] = $nums;
                        break;
                    }
                }
            }
        }

        // 2. Processamento Inteligente (Header + Colunas)
        return $this->processExtractedData($data);
    }

    private function processExtractedData(array $raw): array
    {
        // A. Processa Cabeçalho
        $servingsRaw = $raw['header_servings'] ?? '';
        preg_match('/[0-9]+([.,][0-9]+)?/', $servingsRaw, $matches);
        $servingsPerContainer = str_replace(',', '.', $matches[0] ?? '1');

        $measureRaw = $raw['header_measure'] ?? '';
        // Separa "30g" de "(2 biscoitos)"
        preg_match('/(\d+\s*[g|ml|kg|l]+)/i', $measureRaw, $weightMatch);
        $servingWeight = $weightMatch[0] ?? '0';
        
        // Texto da medida caseira
        $measureText = trim(str_replace($servingWeight, '', $measureRaw));
        $measureText = trim($measureText, "() -");
        $measureData = $this->parseHouseholdMeasure($measureText);

        $finalData = [
            'servings_per_container' => $servingsPerContainer,
            'serving_weight' => $servingWeight,
            'serving_size_quantity' => $measureData['qty'],
            'serving_size_unit' => $measureData['unit'],
        ];

        // B. Processa Linhas (Lógica de 3 colunas vs 2 colunas)
        $rows = $raw['rows'] ?? [];
        foreach ($rows as $dbKey => $numbers) {
            $count = count($numbers);
            $val = 0;
            $dv = 0;

            if ($count >= 3) {
                // [100g, Porção, %VD] -> Pega Porção (1) e %VD (2)
                $val = $numbers[1];
                $dv = $numbers[2];
            } elseif ($count == 2) {
                // [Porção, %VD] -> Pega Porção (0) e %VD (1)
                $val = $numbers[0];
                $dv = $numbers[1];
            } elseif ($count == 1) {
                // [Porção] -> Pega Porção (0)
                $val = $numbers[0];
            }

            $finalData[$dbKey] = (string)$val;
            
            // Campos que têm coluna _dv no banco
            if (in_array($dbKey, ['total_carb', 'added_sugars', 'protein', 'total_fat', 'sat_fat', 'trans_fat', 'fiber', 'sodium', 'cholesterol'])) {
                $finalData[$dbKey . '_dv'] = (string)$dv;
            }
        }

        return $finalData;
    }

    private function parseHouseholdMeasure(string $text): array
    {
        if (empty($text)) return ['qty' => '1', 'unit' => 'Unit'];

        $translatedUnit = 'Unit'; 
        foreach ($this->unitMap as $regex => $englishUnit) {
            if (preg_match($regex, $text)) {
                $translatedUnit = $englishUnit;
                break;
            }
        }

        $qty = '1';
        if (preg_match('/(\d+\s+\d+\/\d+|\d+\/\d+|\d+[\.,]\d+|\d+)/', $text, $matches)) {
            $qty = trim($matches[0]);
        }

        return ['qty' => $qty, 'unit' => $translatedUnit];
    }

    private function queryVision(string $model, string $prompt, string $image, int $timeout): ?string
    {
        try {
            $response = Http::timeout($timeout)->connectTimeout(5)->post("{$this->host}/api/chat", [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $prompt, 'images' => [$image]]],
                'stream' => false, 
                // Removemos 'format' => 'json' para permitir texto livre
                'options' => ['temperature' => 0.0, 'num_ctx' => 4096]
            ]);
            return $response->successful() ? $response->json('message.content') : null;
        } catch (\Exception $e) { Log::error("Ollama Exception: " . $e->getMessage()); return null; }
    }
    
    // Método completion restaurado para tradução
    public function completion(string $prompt, int $timeoutSeconds = 60): ?string 
    {
        try {
            $payload = [
                'model' => $this->textModel,
                'stream' => false,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'options' => ['temperature' => 0.0]
            ];
            $response = Http::timeout($timeoutSeconds)->post("{$this->host}/api/chat", $payload);
            return $response->successful() ? $response->json('message.content') : null;
        } catch (\Exception $e) { return null; }
    }
}