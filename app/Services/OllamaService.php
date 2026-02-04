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
        // Garante que o IP do log seja usado se não estiver no env
        $this->host = rtrim(env('OLLAMA_HOST', 'http://100.89.133.69:11434'), '/');

        // CORREÇÃO 1: Default atualizado para o modelo existente
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:8b');
        $this->textModel = env('OLLAMA_TEXT_MODEL', 'gemma2:latest');
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 300): ?array
    {
        // Prompt ajustado para exigir a coluna %VD
        $prompt = <<<EOT
Analise esta imagem da Tabela Nutricional.
Sua missão é extrair dados para popular um banco SQL, focando na coluna "PORÇÃO" e na coluna "%VD".

REGRAS:
1. Extraia o valor da PORÇÃO (ex: "10g") e o %VD (ex: "5") correspondente.
2. Ignore a coluna "100g". Foque na porção definida no topo.
3. Se o %VD não existir ou for zero, retorne 0 ou null.
4. Extraia Ingredientes e Alérgicos se visíveis.

Retorne APENAS JSON estrito (sem markdown):
{
  "tamanho_porcao": "texto (ex: 30g)",
  "porcoes_embalagem": "texto (ex: aprox. 5)",
  
  // Macros (Valor Absoluto e %VD)
  "calorias": 0,
  "carboidratos": 0.0,
  "carboidratos_vd": 0,    // %VD
  "acucares_totais": 0.0,
  "acucares_adicionados": 0.0,
  "acucares_adicionados_vd": 0, // %VD
  "poliois": 0.0,
  "proteinas": 0.0,
  "proteinas_vd": 0,       // %VD
  "gorduras_totais": 0.0,
  "gorduras_totais_vd": 0, // %VD
  "gorduras_saturadas": 0.0,
  "gorduras_saturadas_vd": 0, // %VD
  "gorduras_trans": 0.0,
  "gorduras_trans_vd": 0, // %VD (geralmente vazio)
  "gorduras_mono": 0.0,
  "gorduras_poli": 0.0,
  "fibra": 0.0,
  "fibra_vd": 0,           // %VD
  "sodio": 0.0,
  "sodio_vd": 0,           // %VD
  "colesterol": 0.0,
  "colesterol_vd": 0,      // %VD

  // Micronutrientes (Geralmente só %VD ou mg)
  "calcio": 0.0,
  "ferro": 0.0,
  "potassio": 0.0,
  "vitamina_d": 0.0,
  "vitamina_a": 0.0,
  "vitamina_c": 0.0,
  
  // Textos
  "ingredientes": "texto completo",
  "alergicos_contem": "texto",
  "alergicos_pode_conter": "texto"
}
EOT;

        // Executa a query (usando o método corrigido anteriormente)
        $rawResponse = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$rawResponse)
            return null;

        $jsonData = $this->robustJsonDecode($rawResponse);
        if (!$jsonData)
            return null;

        return $this->mapPortugueseKeysToSchema($jsonData);
    }

    private function mapPortugueseKeysToSchema(array $ptData): array
    {
        // Helper para limpar números (valores absolutos)
        $cleanNum = function ($key) use ($ptData) {
            if (!isset($ptData[$key]) || $ptData[$key] === null)
                return null;
            $val = preg_replace('/[^0-9,\.-]/', '', (string) $ptData[$key]);
            $val = str_replace(',', '.', $val);
            return is_numeric($val) ? (string) $val : null;
        };

        // Helper específico para %VD (inteiros ou decimais simples)
        $cleanDV = function ($key) use ($ptData) {
            if (!isset($ptData[$key]))
                return null;
            // Remove % e letras
            $val = preg_replace('/[^0-9,\.-]/', '', (string) $ptData[$key]);
            $val = str_replace(',', '.', $val);
            return ($val === '' || $val === null) ? null : (string) $val;
        };

        $cleanText = fn($key) => isset($ptData[$key]) ? trim((string) $ptData[$key]) : null;

        return [
            // Identificação da Porção
            'serving_weight' => $cleanText('tamanho_porcao'),
            'servings_per_container' => $cleanText('porcoes_embalagem'),

            // Calorias
            'calories' => $cleanNum('calorias'),

            // Carboidratos
            'total_carb' => $cleanNum('carboidratos'),
            'total_carb_dv' => $cleanDV('carboidratos_vd'), // Mapeado

            // Açúcares
            'total_sugars' => $cleanNum('acucares_totais'),
            'added_sugars' => $cleanNum('acucares_adicionados'),
            'added_sugars_dv' => $cleanDV('acucares_adicionados_vd'), // Mapeado
            'sugar_alcohol' => $cleanNum('poliois'),

            // Proteínas
            'protein' => $cleanNum('proteinas'),
            'protein_dv' => $cleanDV('proteinas_vd'), // Mapeado

            // Gorduras
            'total_fat' => $cleanNum('gorduras_totais'),
            'total_fat_dv' => $cleanDV('gorduras_totais_vd'), // Mapeado

            'sat_fat' => $cleanNum('gorduras_saturadas'),
            'sat_fat_dv' => $cleanDV('gorduras_saturadas_vd'), // Mapeado

            'trans_fat' => $cleanNum('gorduras_trans'),
            'trans_fat_dv' => $cleanDV('gorduras_trans_vd'), // Mapeado

            'mono_fat' => $cleanNum('gorduras_mono'),
            'poly_fat' => $cleanNum('gorduras_poli'),

            // Outros
            'fiber' => $cleanNum('fibra'),
            'fiber_dv' => $cleanDV('fibra_vd'), // Mapeado

            'sodium' => $cleanNum('sodio'),
            'sodium_dv' => $cleanDV('sodio_vd'), // Mapeado

            'cholesterol' => $cleanNum('colesterol'),
            'cholesterol_dv' => $cleanDV('colesterol_vd'), // Mapeado

            // Micros (Exemplos, expanda conforme necessário)
            'calcium' => $cleanNum('calcio'),
            'iron' => $cleanNum('ferro'),
            'potassium' => $cleanNum('potassio'),
            'vitamin_d' => $cleanNum('vitamina_d'),
            'vitamin_c' => $cleanNum('vitamina_c'),
            'vitamin_a' => $cleanNum('vitamina_a'),

            // Textos
            'ingredients' => $cleanText('ingredientes'),
            'allergens_contains' => $cleanText('alergicos_contem'),
            'allergens_may_contain' => $cleanText('alergicos_pode_conter'),
        ];
    }
}