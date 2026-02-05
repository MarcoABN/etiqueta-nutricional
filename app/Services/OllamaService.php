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
        // Micros essenciais
        'VAL_CALCIO' => 'calcium',
        'VAL_FERRO' => 'iron',
        'VAL_POTASSIO' => 'potassium',
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
        // O modelo 30b é mandatório para essa lógica semântica funcionar bem
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:30b'); 
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest');
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        // PROMPT "UNIVERSAL" REFINADO
        $prompt = <<<EOT
Analise esta Tabela Nutricional (pode ser Vertical, Horizontal ou Linear).
Sua missão: Encontrar o valor da PORÇÃO (Serving Size) para cada nutriente.

REGRAS DE OURO:
1. IGNORE a coluna/referência "100g" ou "100ml". Foque apenas na PORÇÃO.
2. Em tabelas HORIZONTAIS, o valor está logo após o nome (ex: "Carboidratos 10g").
3. O %VD pode estar em coluna separada OU entre parênteses (ex: "Sódio 50mg (2%)").
4. Se o valor for "Zero", "Não contém" ou "-", retorne 0.

Preencha o gabarito abaixo com os valores encontrados (apenas números e sufixos):

HEADER_PORCAO_EMB: <Texto de Porções por embalagem>
HEADER_MEDIDA: <Texto da medida caseira ex: 30g (2 biscoitos)>

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
VAL_CALCIO: <valor>
VAL_FERRO: <valor>
VAL_POTASSIO: <valor>

Exemplo de Saída Esperada:
HEADER_PORCAO_EMB: Aprox. 5
HEADER_MEDIDA: 25g (1 unidade)
VAL_CALORIAS: 130
VAL_CARBO: 15g | VD: 5
VAL_GORDURA_TOTAL: 3.5g | VD: 6
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

            // 1. Processa Cabeçalhos de Porção
            if (str_starts_with($line, 'HEADER_PORCAO_EMB:')) {
                $raw = trim(substr($line, 18));
                preg_match('/[0-9]+([.,][0-9]+)?/', $raw, $matches);
                $finalData['servings_per_container'] = str_replace(',', '.', $matches[0] ?? '1');
            }
            
            elseif (str_starts_with($line, 'HEADER_MEDIDA:')) {
                $raw = trim(substr($line, 14));
                // Extrai peso métrico (ex: 30g, 200ml)
                preg_match('/(\d+\s*[g|ml|kg|l]+)/i', $raw, $weightMatch);
                $finalData['serving_weight'] = $weightMatch[0] ?? '';
                
                // O restante é a medida caseira (remove o peso encontrado)
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
                        // Remove o prefixo da chave para processar o conteúdo
                        $content = trim(substr($line, strlen($aiKey) + 1));
                        
                        // Divide valor principal e VD pelo pipe "|"
                        $parts = explode('|', $content);
                        $mainValueRaw = $parts[0];
                        
                        // Busca VD na segunda parte OU tenta achar padrão "(XX%)" na primeira parte
                        $dvValueRaw = $parts[1] ?? '';
                        if (empty($dvValueRaw) && preg_match('/\(\s*(\d+)\s*%\s*\)/', $mainValueRaw, $dvMatch)) {
                            $dvValueRaw = $dvMatch[1]; // Pega o valor dentro do parênteses
                        }

                        // Sanitiza e salva
                        $finalData[$dbKey] = $this->extractNumber($mainValueRaw);

                        // Se tiver campo de DV no banco
                        $dvDbKey = $dbKey . '_dv'; // Padrão
                        if (isset($this->keyMap[$aiKey . '_VD'])) {
                             // Caso exista mapeamento explicito (raro com essa logica)
                             // mas mantemos o fallback
                             continue; 
                        }
                        
                        // Verifica se essa coluna _dv existe no Schema mentalmente (macros principais)
                        if (in_array($dbKey, ['total_carb', 'added_sugars', 'protein', 'total_fat', 'sat_fat', 'trans_fat', 'fiber', 'sodium', 'cholesterol'])) {
                            $finalData[$dvDbKey] = $this->extractNumber($dvValueRaw);
                        }
                        break;
                    }
                }
            }
        }
        
        return $finalData;
    }

    /**
     * Extrai apenas números de strings sujas (ex: "10 g" -> "10", "2,5%" -> "2.5")
     */
    private function extractNumber(string $str): string
    {
        // Remove unidades comuns para evitar confusão
        $str = preg_replace('/(kcal|cal|mg|mcg|g|ml|%)/i', '', $str);
        
        // Mantém apenas números, ponto, vírgula e traço
        $val = preg_replace('/[^0-9,\.-]/', '', $str);
        $val = str_replace(',', '.', $val);
        
        // Se for string vazia, ponto ou traço isolado, vira 0
        if ($val === '' || $val === '.' || $val === '-') return '0';
        
        return $val;
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