<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $visionModel;
    protected string $textModel;

    // Mapeamento Chave IA -> Chave Banco
    protected array $keyMap = [
        'VAL_CALORIAS' => 'calories',
        'VAL_CARBO' => 'total_carb',
        'VAL_CARBO_VD' => 'total_carb_dv',
        'VAL_ACUCAR_TOTAL' => 'total_sugars',
        'VAL_ACUCAR_ADD' => 'added_sugars',
        'VAL_ACUCAR_ADD_VD' => 'added_sugars_dv',
        'VAL_PROTEINA' => 'protein',
        'VAL_PROTEINA_VD' => 'protein_dv',
        'VAL_GORDURA_TOTAL' => 'total_fat',
        'VAL_GORDURA_TOTAL_VD' => 'total_fat_dv',
        'VAL_GORDURA_SAT' => 'sat_fat',
        'VAL_GORDURA_SAT_VD' => 'sat_fat_dv',
        'VAL_GORDURA_TRANS' => 'trans_fat',
        'VAL_FIBRA' => 'fiber',
        'VAL_FIBRA_VD' => 'fiber_dv',
        'VAL_SODIO' => 'sodium',
        'VAL_SODIO_VD' => 'sodium_dv',
        'VAL_COLESTEROL' => 'cholesterol',
        
        // Micros
        'VAL_CALCIO' => 'calcium',
        'VAL_FERRO' => 'iron',
        'VAL_POTASSIO' => 'potassium',
        'VAL_VIT_A' => 'vitamin_a',
        'VAL_VIT_C' => 'vitamin_c',
        'VAL_VIT_D' => 'vitamin_d',
    ];

    // ORDEM IMPORTA: Termos específicos primeiro, genéricos por último
    protected array $unitMap = [
        '/x[ií]caras?/i'    => 'Cup',        // Prioridade alta
        '/colher.*sopa/i'   => 'Tablespoon',
        '/colher.*ch[aá]/i' => 'Teaspoon',
        '/copo/i'           => 'Cup',
        '/fatia/i'          => 'Slice',
        '/pote/i'           => 'Container',
        '/garrafa/i'        => 'Bottle',
        '/lata/i'           => 'Can',
        '/biscoito/i'       => 'Cookie',
        '/barra/i'          => 'Bar',
        '/unidade/i'        => 'Piece',      // Genérico (Deixar pro final)
        '/embalagem/i'      => 'Package',    // Genérico
    ];

    public function __construct()
    {
        $this->host = rtrim(env('OLLAMA_HOST', 'http://100.89.133.69:11434'), '/');
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:30b'); 
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest'); 
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        // Prompt Semântico Refinado
        $prompt = <<<EOT
Analise esta Tabela Nutricional (Vertical, Horizontal ou Linear).
Encontre o valor da PORÇÃO (Serving Size) para cada item e o %VD (Percentual Diário).

REGRAS:
1. Ignore a base "100g". Foque na PORÇÃO.
2. Tente encontrar o %VD. Ele geralmente está na última coluna ou entre parênteses "(5%)".
3. Se não encontrar o %VD, deixe vazio após o pipe.

Formato de Saída Obrigatório:
HEADER_PORCAO_EMB: <texto>
HEADER_MEDIDA: <texto>

VAL_CALORIAS: <valor>
VAL_CARBO: <valor> | VD: <vd>
VAL_ACUCAR_TOTAL: <valor>
VAL_ACUCAR_ADD: <valor> | VD: <vd>
VAL_PROTEINA: <valor> | VD: <vd>
VAL_GORDURA_TOTAL: <valor> | VD: <vd>
VAL_GORDURA_SAT: <valor> | VD: <vd>
VAL_GORDURA_TRANS: <valor>
VAL_FIBRA: <valor> | VD: <vd>
VAL_SODIO: <valor> | VD: <vd>
VAL_COLESTEROL: <valor> | VD: <vd>
VAL_CALCIO: <valor> | VD: <vd>
VAL_FERRO: <valor> | VD: <vd>
VAL_POTASSIO: <valor> | VD: <vd>
VAL_VIT_D: <valor> | VD: <vd>

Exemplo:
VAL_CARBO: 15g | VD: 5
VAL_SODIO: 100mg (2%) | VD: 2
EOT;

        $response = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$response) return null;
        
        Log::info("Ollama Response:\n" . substr($response, 0, 500));

        return $this->parseSemanticResponse($response);
    }

    private function parseSemanticResponse(string $text): array
    {
        $lines = explode("\n", $text);
        $finalData = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // 1. Cabeçalhos
            if (str_starts_with($line, 'HEADER_PORCAO_EMB:')) {
                $raw = trim(substr($line, 18));
                preg_match('/[0-9]+([.,][0-9]+)?/', $raw, $matches);
                $finalData['servings_per_container'] = str_replace(',', '.', $matches[0] ?? '1');
            }
            elseif (str_starts_with($line, 'HEADER_MEDIDA:')) {
                $raw = trim(substr($line, 14));
                preg_match('/(\d+\s*[g|ml|kg|l]+)/i', $raw, $weightMatch);
                $finalData['serving_weight'] = $weightMatch[0] ?? '';
                
                $measureText = trim(str_replace($finalData['serving_weight'], '', $raw));
                $measureText = trim($measureText, "() -");
                $measureData = $this->parseHouseholdMeasure($measureText);
                
                $finalData['serving_size_quantity'] = $measureData['qty'];
                $finalData['serving_size_unit'] = $measureData['unit'];
            }
            
            // 2. Nutrientes (Parser Híbrido: Pipe ou Regex)
            else {
                foreach ($this->keyMap as $aiKey => $dbKey) {
                    if (str_starts_with($line, $aiKey . ':')) {
                        $content = trim(substr($line, strlen($aiKey) + 1));
                        
                        // A. Tenta extrair Valor Principal
                        // Pega o primeiro número que aparecer
                        $mainValue = $this->extractNumber(strtok($content, '|'));
                        $finalData[$dbKey] = $mainValue;

                        // B. Tenta extrair %VD (Mais robusto agora)
                        // Verifica se existe coluna _dv mapeada para este nutriente no banco (mentalmente)
                        if (in_array($dbKey, ['total_carb', 'added_sugars', 'protein', 'total_fat', 'sat_fat', 'trans_fat', 'fiber', 'sodium', 'cholesterol', 'calcium', 'iron', 'potassium', 'vitamin_d'])) {
                            
                            $dvValue = '0';
                            
                            // Estratégia 1: Procura depois do Pipe "|"
                            $parts = explode('|', $content);
                            if (isset($parts[1])) {
                                $dvValue = $this->extractNumber($parts[1]);
                            }
                            
                            // Estratégia 2: Se falhar, procura por padrão "VD" ou "%" na linha toda
                            if ($dvValue === '0' || $dvValue === '') {
                                // Procura "VD: 5" ou "5%" ou "(5)" no final
                                if (preg_match('/(VD:?|%)\s*(\d+[.,]?\d*)/i', $content, $matches)) {
                                    $dvValue = str_replace(',', '.', $matches[2]);
                                } elseif (preg_match('/\(\s*(\d+[.,]?\d*)\s*%\s*\)/', $content, $matches)) {
                                    $dvValue = str_replace(',', '.', $matches[1]);
                                }
                            }

                            $finalData[$dbKey . '_dv'] = $dvValue;
                        }
                        break;
                    }
                }
            }
        }
        
        return $finalData;
    }

    private function extractNumber(string $str): string
    {
        $str = preg_replace('/(kcal|cal|mg|mcg|g|ml|%|VD)/i', '', $str);
        $val = preg_replace('/[^0-9,\.-]/', '', $str);
        $val = str_replace(',', '.', $val);
        return ($val === '' || $val === '.' || $val === '-') ? '0' : $val;
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
                'options' => ['temperature' => 0.0, 'num_ctx' => 4096]
            ]);
            return $response->successful() ? $response->json('message.content') : null;
        } catch (\Exception $e) { Log::error($e->getMessage()); return null; }
    }
    
    // MÉTODO REATIVADO: Crucial para a tradução de nomes
    public function completion(string $prompt, int $timeoutSeconds = 60): ?string 
    {
        try {
            $payload = [
                'model' => $this->textModel,
                'stream' => false,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'options' => ['temperature' => 0.0]
            ];
            $response = Http::timeout($timeoutSeconds)->connectTimeout(5)->post("{$this->host}/api/chat", $payload);
            return $response->successful() ? $response->json('message.content') : null;
        } catch (\Exception $e) { 
            Log::error("Ollama Translation Error: " . $e->getMessage());
            return null; 
        }
    }
}