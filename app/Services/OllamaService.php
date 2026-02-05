<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
//Commit estável 2
class OllamaService
{
    protected string $host;
    protected string $visionModel;
    protected string $textModel;

    // Mapeamento de Chaves da IA para o Banco de Dados
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
        // Adicione micros se necessário
    ];

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
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:30b'); 
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest');
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        // PROMPT SEMÂNTICO "CAÇA-PALAVRAS"
        // Instruímos a IA a achar o rótulo onde quer que esteja e pegar o valor da PORÇÃO.
        $prompt = <<<EOT
Analise esta Tabela Nutricional. Ela pode estar no formato VERTICAL, HORIZONTAL ou QUEBRADO (duas colunas).
Sua missão é encontrar o valor correspondente à PORÇÃO (Serving) para cada item.

REGRAS VISUAIS:
1. Ignore valores da coluna "100g" ou "100ml". Queremos apenas o valor da PORÇÃO.
2. O valor pode estar AO LADO (direita) ou ABAIXO do nome do nutriente.
3. Se houver dois números na mesma célula (ex: "VD 5%"), separe o valor absoluto do %VD.

Retorne APENAS as linhas abaixo preenchidas (Formato Texto):

HEADER_PORCAO_EMB: <Texto exato de Porções por embalagem>
HEADER_MEDIDA: <Texto exato da medida caseira e peso>

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

Exemplo de Saída:
HEADER_PORCAO_EMB: Cerca de 6
HEADER_MEDIDA: 30g (2 biscoitos)
VAL_CALORIAS: 140
VAL_CARBO: 18g | VD: 6
VAL_GORDURA_TOTAL: 4.2g | VD: 8
EOT;

        $response = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$response) return null;
        
        Log::info("Ollama Semantic Response:\n" . substr($response, 0, 500));

        return $this->parseSemanticResponse($response);
    }

    private function parseSemanticResponse(string $text): array
    {
        $lines = explode("\n", $text);
        $finalData = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // 1. Processa Cabeçalhos
            if (str_starts_with($line, 'HEADER_PORCAO_EMB:')) {
                $raw = trim(substr($line, 18));
                preg_match('/[0-9]+([.,][0-9]+)?/', $raw, $matches);
                $finalData['servings_per_container'] = str_replace(',', '.', $matches[0] ?? '1');
            }
            
            elseif (str_starts_with($line, 'HEADER_MEDIDA:')) {
                $raw = trim(substr($line, 14));
                // Separa peso (30g) de medida (2 biscoitos)
                preg_match('/(\d+\s*[g|ml|kg|l]+)/i', $raw, $weightMatch);
                $finalData['serving_weight'] = $weightMatch[0] ?? '';
                
                $measureText = trim(str_replace($finalData['serving_weight'], '', $raw));
                $measureText = trim($measureText, "() -");
                $measureData = $this->parseHouseholdMeasure($measureText);
                
                $finalData['serving_size_quantity'] = $measureData['qty'];
                $finalData['serving_size_unit'] = $measureData['unit'];
            }

            // 2. Processa Nutrientes (Chave: Valor | VD: Valor)
            else {
                foreach ($this->keyMap as $aiKey => $dbKey) {
                    if (str_starts_with($line, $aiKey . ':')) {
                        // Remove a chave para processar o conteúdo
                        $content = trim(substr($line, strlen($aiKey) + 1));
                        
                        // Verifica se tem pipe "|" separando o VD
                        $parts = explode('|', $content);
                        $mainValueRaw = $parts[0];
                        $dvValueRaw = $parts[1] ?? '';

                        // Limpa Valor Principal
                        $finalData[$dbKey] = $this->extractNumber($mainValueRaw);

                        // Limpa Valor VD (se o banco tiver coluna _dv para este item)
                        if (str_contains($dbKey, '_dv')) {
                            // Se a chave já for _dv, pega direto
                             $finalData[$dbKey] = $this->extractNumber($mainValueRaw);
                        } elseif (isset($this->keyMap[$aiKey . '_VD'])) {
                             // Lógica para pegar o VD da parte separada por pipe (VD: x)
                             // Mas meu prompt pede "VAL_CARBO: x | VD: y"
                             // Então preciso popular o campo total_carb_dv
                             $dvDbKey = $dbKey . '_dv'; // Convenção: total_carb -> total_carb_dv
                             $finalData[$dvDbKey] = $this->extractNumber($dvValueRaw);
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
        // Remove tudo que não é número, ponto ou vírgula
        $val = preg_replace('/[^0-9,\.-]/', '', $str);
        $val = str_replace(',', '.', $val);
        return ($val === '' || $val === '.') ? '0' : $val;
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
    
    public function completion(string $prompt, int $timeoutSeconds = 60): ?string { return null; }
}