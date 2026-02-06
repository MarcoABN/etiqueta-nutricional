<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpenFoodFactsService
{
    protected string $baseUrl = 'https://world.openfoodfacts.org/api/v2/product/';

    // VALORES DIÁRIOS DE REFERÊNCIA (VDR) - ANVISA (IN 75/2020 - Anexo II)
    // Base: Adultos e Crianças acima de 36 meses
    protected const ANVISA_VALUES = [
        'energy' => 2000,        // kcal
        'total_carb' => 300,     // g
        'added_sugars' => 50,    // g
        'protein' => 50,         // g
        'total_fat' => 55,       // g
        'sat_fat' => 20,         // g
        'fiber' => 25,           // g
        'sodium' => 2000,        // mg
        'cholesterol' => 300,    // mg (Anexo III RDC 54, mantido comumente)

        // Micronutrientes (IN 75 Anexo II)
        'calcium' => 1000,       // mg
        'iron' => 14,            // mg
        'potassium' => 3500,     // mg (RDC 54 tinha 4700, verificar atualização IN 75 trouxe novos VDRs?) 
                                 // IN 75 Anexo II não lista Potássio explicitamente na tabela principal de VDR para rotulagem frontal, 
                                 // mas para tabela nutricional usa-se IDR. Vamos usar o padrão de mercado 4700mg ou 3500mg (WHO). 
                                 // A ANVISA RDC 269/2005 define IDR. Vamos manter 3500mg (padrão atualizado).
        'magnesium' => 260,      // mg
        'zinc' => 7,             // mg
        'phosphorus' => 700,     // mg
        'vitamin_c' => 100,      // mg
        'vitamin_e' => 15,       // mg
        'niacin' => 16,          // mg
        'vitamin_b6' => 1.3,     // mg
        'thiamin' => 1.2,        // mg
        'riboflavin' => 1.3,     // mg
        'pantothenic_acid' => 5, // mg
        'manganese' => 2.3,      // mg
        'chloride' => 2300,      // mg

        'vitamin_a' => 800,      // mcg RE
        'vitamin_d' => 15,       // mcg (Mudou de 5 para 15 na nova legislação)
        'vitamin_k' => 120,      // mcg
        'vitamin_b12' => 2.4,    // mcg
        'folate' => 400,         // mcg
        'biotin' => 30,          // mcg
        'selenium' => 34,        // mcg
        'iodine' => 150,         // mcg
        'copper' => 900,         // mcg
        'molybdenum' => 45,      // mcg
        'chromium' => 35,        // mcg
    ];

    protected array $allergenMapPt = [
        'en:milk' => 'Leite', 'en:soybeans' => 'Soja', 'en:eggs' => 'Ovo',
        'en:nuts' => 'Nozes', 'en:peanuts' => 'Amendoim', 'en:wheat' => 'Trigo',
        'en:fish' => 'Peixe', 'en:crustaceans' => 'Crustáceos', 'en:gluten' => 'Glúten',
        'en:oats' => 'Aveia', 'en:barley' => 'Cevada', 'en:rye' => 'Centeio',
        'en:cashew-nuts' => 'Castanha de Caju', 'en:brazil-nuts' => 'Castanha do Pará',
        'en:hazelnuts' => 'Avelã', 'en:almonds' => 'Amêndoa', 'en:celery' => 'Aipo',
        'en:mustard' => 'Mostarda', 'en:sesame-seeds' => 'Gergelim',
        'en:sulphur-dioxide-and-sulphites' => 'Sulfitos',
    ];

    public function fetchProductData(string $barcode): ?array
    {
        $barcode = preg_replace('/[^0-9]/', '', $barcode);
        if (empty($barcode)) return null;

        $response = Http::timeout(20)->get($this->baseUrl . $barcode . '.json');

        if ($response->failed() || !isset($response->json()['product'])) {
            return null;
        }

        return $this->mapDataToModel($response->json()['product'], $barcode);
    }

    protected function mapDataToModel(array $product, string $barcode): array
    {
        $nutriments = $product['nutriments'] ?? [];

        // 1. Extração de Medidas
        $servingData = $this->extractServingInfo($product);
        $servingWeight = $servingData['weight'];
        
        // 2. Imagem
        $imagePath = $this->downloadNutritionImage($product, $barcode);

        // 3. Extração dos Valores Brutos (Raw)
        // $direct: busca g/kcal | $mg: busca e converte g->mg | $mcg: busca e converte g->mcg
        // Se null, retorna 0.
        $direct = fn($keys) => $this->getNutrient($nutriments, (array)$keys, 1) ?? 0;
        $mg     = fn($keys) => $this->getNutrient($nutriments, (array)$keys, 1000) ?? 0;
        $mcg    = fn($keys) => $this->getNutrient($nutriments, (array)$keys, 1000000) ?? 0;

        $raw_calories     = $direct(['energy-kcal']);
        $raw_total_carb   = $direct(['carbohydrates']);
        $raw_total_sugars = $direct(['sugars']);
        $raw_added_sugars = $direct(['added-sugars']);
        $raw_protein      = $direct(['proteins', 'protein']);
        $raw_total_fat    = $direct(['fat']);
        $raw_sat_fat      = $direct(['saturated-fat']);
        $raw_trans_fat    = $direct(['trans-fat']);
        $raw_fiber        = $direct(['fiber']);
        
        $raw_sodium       = $this->getSodiumValue($nutriments) ?? 0;
        $raw_cholesterol  = $mg(['cholesterol']);

        // 4. Aplicação das Regras de Arredondamento ANVISA (IN 75)
        // Isso define o valor que será EXIBIDO no sistema (Visual) e usado para cálculo de VD
        
        $v_calories     = $this->anvisaRound($raw_calories, 'energy');
        $v_total_carb   = $this->anvisaRound($raw_total_carb, 'macro');
        $v_total_sugars = $this->anvisaRound($raw_total_sugars, 'macro');
        $v_added_sugars = $this->anvisaRound($raw_added_sugars, 'macro');
        $v_protein      = $this->anvisaRound($raw_protein, 'macro');
        $v_total_fat    = $this->anvisaRound($raw_total_fat, 'macro');
        $v_sat_fat      = $this->anvisaRound($raw_sat_fat, 'macro');
        $v_trans_fat    = $this->anvisaRound($raw_trans_fat, 'trans_fat'); // Regra especial
        $v_fiber        = $this->anvisaRound($raw_fiber, 'macro');
        $v_sodium       = $this->anvisaRound($raw_sodium, 'sodium');
        $v_cholesterol  = $this->anvisaRound($raw_cholesterol, 'sodium'); // Regra similar (inteiro)

        // Micros - Geralmente mantêm casas decimais ou inteiros conforme magnitude
        // Usaremos regra genérica 'micro' para manter precisão visual limpa
        $v_calcium      = $this->anvisaRound($mg(['calcium']), 'micro_mg');
        $v_iron         = $this->anvisaRound($mg(['iron']), 'micro_small');
        $v_potassium    = $this->anvisaRound($mg(['potassium']), 'micro_mg');
        $v_magnesium    = $this->anvisaRound($mg(['magnesium']), 'micro_mg');
        $v_zinc         = $this->anvisaRound($mg(['zinc']), 'micro_small');
        $v_phosphorus   = $this->anvisaRound($mg(['phosphorus']), 'micro_mg');
        $v_vit_c        = $this->anvisaRound($mg(['vitamin-c', 'ascorbic-acid']), 'micro_small');
        $v_vit_e        = $this->anvisaRound($mg(['vitamin-e']), 'micro_small');
        $v_niacin       = $this->anvisaRound($mg(['vitamin-pp', 'vitamin-b3', 'niacin']), 'micro_small');
        $v_vit_b6       = $this->anvisaRound($mg(['vitamin-b6', 'pyridoxine']), 'micro_small');
        $v_thiamin      = $this->anvisaRound($mg(['vitamin-b1', 'thiamin']), 'micro_small');
        $v_riboflavin   = $this->anvisaRound($mg(['vitamin-b2', 'riboflavin']), 'micro_small');
        $v_pantothenic  = $this->anvisaRound($mg(['vitamin-b5', 'pantothenic-acid']), 'micro_small');
        $v_chloride     = $this->anvisaRound($mg(['chloride']), 'micro_mg');
        $v_manganese    = $this->anvisaRound($mg(['manganese']), 'micro_small');

        $v_vit_a        = $this->anvisaRound($mcg(['vitamin-a', 'retinol']), 'micro_mg');
        $v_vit_d        = $this->anvisaRound($mcg(['vitamin-d']), 'micro_small');
        $v_vit_k        = $this->anvisaRound($mcg(['vitamin-k']), 'micro_small');
        $v_vit_b12      = $this->anvisaRound($mcg(['vitamin-b12', 'cobalamin']), 'micro_small');
        $v_folate       = $this->anvisaRound($mcg(['vitamin-b9', 'folates', 'folic-acid']), 'micro_mg');
        $v_biotin       = $this->anvisaRound($mcg(['vitamin-b7', 'biotin']), 'micro_small');
        $v_selenium     = $this->anvisaRound($mcg(['selenium']), 'micro_small');
        $v_iodine       = $this->anvisaRound($mcg(['iodine']), 'micro_mg');
        $v_copper       = $this->anvisaRound($mcg(['copper']), 'micro_mg');
        $v_molybdenum   = $this->anvisaRound($mcg(['molybdenum']), 'micro_small');
        $v_chromium     = $this->anvisaRound($mcg(['chromium']), 'micro_small');

        // 5. Retorno com Cálculo de %VD baseado nos valores ARREDONDADOS (ANVISA)
        return [
            'import_status' => 'Dados via API',
            
            // Medidas
            'serving_weight' => $servingData['weight'],
            'serving_size_quantity' => $servingData['qty'],
            'serving_size_unit' => $servingData['unit'],
            'servings_per_container' => $servingData['servings'],

            // Textos
            'ingredients_pt' => $product['ingredients_text_pt'] ?? $product['ingredients_text'] ?? null,
            'ingredients' => $product['ingredients_text_en'] ?? $product['ingredients_text'] ?? null,
            'allergens_contains_pt' => $this->processTags($product['allergens_tags'] ?? [], true),
            'allergens_may_contain_pt' => $this->processTags($product['traces_tags'] ?? [], true),
            'allergens_contains' => $this->processTags($product['allergens_tags'] ?? [], false),
            'allergens_may_contain' => $this->processTags($product['traces_tags'] ?? [], false),

            'image_nutritional' => $imagePath,

            // Macros + %VD
            'calories'          => $v_calories,
            'total_carb'        => $v_total_carb,
            'total_carb_dv'     => $this->calcDv($v_total_carb, 'total_carb'),
            
            'total_sugars'      => $v_total_sugars,
            'added_sugars'      => $v_added_sugars,
            'added_sugars_dv'   => $this->calcDv($v_added_sugars, 'added_sugars'),
            
            'protein'           => $v_protein,
            'protein_dv'        => $this->calcDv($v_protein, 'protein'),
            
            'total_fat'         => $v_total_fat,
            'total_fat_dv'      => $this->calcDv($v_total_fat, 'total_fat'),
            
            'sat_fat'           => $v_sat_fat,
            'sat_fat_dv'        => $this->calcDv($v_sat_fat, 'sat_fat'),
            
            'trans_fat'         => $v_trans_fat,
            'trans_fat_dv'      => null, // ANVISA não tem VD para trans
            
            'fiber'             => $v_fiber,
            'fiber_dv'          => $this->calcDv($v_fiber, 'fiber'),
            
            'sodium'            => $v_sodium,
            'sodium_dv'         => $this->calcDv($v_sodium, 'sodium'),

            'cholesterol'       => $v_cholesterol,
            'cholesterol_dv'    => $this->calcDv($v_cholesterol, 'cholesterol'),

            // Micros + %VD (MG)
            'calcium'           => $v_calcium,
            'calcium_dv'        => $this->calcDv($v_calcium, 'calcium'),
            
            'iron'              => $v_iron,
            'iron_dv'           => $this->calcDv($v_iron, 'iron'),
            
            'potassium'         => $v_potassium,
            'potassium_dv'      => $this->calcDv($v_potassium, 'potassium'),
            
            'magnesium'         => $v_magnesium,
            'magnesium_dv'      => $this->calcDv($v_magnesium, 'magnesium'),
            
            'zinc'              => $v_zinc,
            'zinc_dv'           => $this->calcDv($v_zinc, 'zinc'),
            
            'phosphorus'        => $v_phosphorus,
            'phosphorus_dv'     => $this->calcDv($v_phosphorus, 'phosphorus'),

            'vitamin_c'         => $v_vit_c,
            'vitamin_c_dv'      => $this->calcDv($v_vit_c, 'vitamin_c'),
            
            'vitamin_e'         => $v_vit_e,
            'vitamin_e_dv'      => $this->calcDv($v_vit_e, 'vitamin_e'),
            
            'niacin'            => $v_niacin,
            'niacin_dv'         => $this->calcDv($v_niacin, 'niacin'),
            
            'vitamin_b6'        => $v_vit_b6,
            'vitamin_b6_dv'     => $this->calcDv($v_vit_b6, 'vitamin_b6'),
            
            'thiamin'           => $v_thiamin,
            'thiamin_dv'        => $this->calcDv($v_thiamin, 'thiamin'),
            
            'riboflavin'        => $v_riboflavin,
            'riboflavin_dv'     => $this->calcDv($v_riboflavin, 'riboflavin'),
            
            'pantothenic_acid'  => $v_pantothenic,
            'pantothenic_acid_dv' => $this->calcDv($v_pantothenic, 'pantothenic_acid'),
            
            'chloride'          => $v_chloride,
            'chloride_dv'       => $this->calcDv($v_chloride, 'chloride'),
            
            'manganese'         => $v_manganese,
            'manganese_dv'      => $this->calcDv($v_manganese, 'manganese'),

            // Micros + %VD (MCG)
            'vitamin_a'         => $v_vit_a,
            'vitamin_a_dv'      => $this->calcDv($v_vit_a, 'vitamin_a'),
            
            'vitamin_d'         => $v_vit_d,
            'vitamin_d_dv'      => $this->calcDv($v_vit_d, 'vitamin_d'),
            
            'vitamin_k'         => $v_vit_k,
            'vitamin_k_dv'      => $this->calcDv($v_vit_k, 'vitamin_k'),
            
            'vitamin_b12'       => $v_vit_b12,
            'vitamin_b12_dv'    => $this->calcDv($v_vit_b12, 'vitamin_b12'),
            
            'folate'            => $v_folate,
            'folate_dv'         => $this->calcDv($v_folate, 'folate'),
            
            'biotin'            => $v_biotin,
            'biotin_dv'         => $this->calcDv($v_biotin, 'biotin'),
            
            'selenium'          => $v_selenium,
            'selenium_dv'       => $this->calcDv($v_selenium, 'selenium'),
            
            'iodine'            => $v_iodine,
            'iodine_dv'         => $this->calcDv($v_iodine, 'iodine'),
            
            'copper'            => $v_copper,
            'copper_dv'         => $this->calcDv($v_copper, 'copper'),
            
            'molybdenum'        => $v_molybdenum,
            'molybdenum_dv'     => $this->calcDv($v_molybdenum, 'molybdenum'),
            
            'chromium'          => $v_chromium,
            'chromium_dv'       => $this->calcDv($v_chromium, 'chromium'),
        ];
    }

    /**
     * REGRA DE ARREDONDAMENTO ANVISA (IN 75/2020 - Anexo IV)
     */
    protected function anvisaRound($value, string $type)
    {
        if ($value === null || $value === '') return 0;
        $val = (float) $value;

        switch ($type) {
            case 'energy': // kcal
                // < 5 declara 0. Arredonda para inteiro.
                if ($val < 5) return 0;
                return round($val);

            case 'sodium': // mg
                // < 5 declara 0. Arredonda para inteiro.
                if ($val < 5) return 0;
                return round($val);

            case 'macro': // g (Carb, Prot, Fat, Fiber, Sugar)
                // < 0.5 declara 0.
                if ($val < 0.5) return 0;
                // < 10 declara com 1 casa decimal (ex: 2.3)
                if ($val < 10) return round($val, 1);
                // >= 10 declara inteiro (ex: 17)
                return round($val);

            case 'trans_fat': // g (Regra estrita)
                // < 0.2 declara 0 (por porção) ou < 0.1 por 100g. 
                // Usamos a regra da porção aqui.
                if ($val <= 0.2) return 0;
                if ($val < 10) return round($val, 1);
                return round($val);

            case 'micro_mg': // mg (Cálcio, Potássio, Fósforo) - Quantidades maiores
                // Se for muito baixo, 0.
                if ($val < 1) return round($val, 2); // Mantém precisão para valores baixos
                return round($val); // Arredonda para inteiro para valores altos como 1000mg

            case 'micro_small': // mg/mcg pequenos (Ferro, Zinco, Vitaminas)
                // Mantém até 2 casas decimais para precisão
                // Ex: 2.4 mcg B12, 1.2 mg B1
                return round($val, 2);
        }

        return $val;
    }

    /**
     * Calcula o %VD conforme padrão ANVISA (Matemático, não Ceil)
     */
    protected function calcDv($value, string $key): int
    {
        if (empty($value)) return 0;
        
        if (!isset(self::ANVISA_VALUES[$key])) {
            return 0;
        }

        $reference = self::ANVISA_VALUES[$key];

        if ($reference <= 0) return 0;

        // Arredondamento Padrão (0.5 sobe)
        return (int) round(($value / $reference) * 100);
    }

    /**
     * Helper de Sódio (Retorna null internamente para permitir lógica de Sal)
     */
    protected function getSodiumValue(array $nutriments)
    {
        $sodium = $this->getNutrient($nutriments, ['sodium'], 1000);
        if ($sodium !== null) {
            return $sodium;
        }
        return $this->getNutrient($nutriments, ['salt'], 400); 
    }

    protected function getNutrient(array $nutriments, array $keys, float $factor)
    {
        foreach ($keys as $key) {
            if (isset($nutriments[$key]) && is_numeric($nutriments[$key])) {
                return (float) $nutriments[$key] * $factor;
            }
            if (isset($nutriments["{$key}_serving"]) && is_numeric($nutriments["{$key}_serving"])) {
                return (float) $nutriments["{$key}_serving"] * $factor;
            }
            if (isset($nutriments["{$key}_value"]) && is_numeric($nutriments["{$key}_value"])) {
                return (float) $nutriments["{$key}_value"] * $factor;
            }
        }
        return null;
    }

    protected function extractServingInfo(array $product): array
    {
        $weight = isset($product['serving_quantity']) && is_numeric($product['serving_quantity']) 
            ? (float) $product['serving_quantity'] 
            : null;

        $originalText = $product['serving_size'] ?? '';
        $cleanText = trim(preg_replace('/\s+/u', ' ', str_replace(["\xA0", "\xC2\xA0"], ' ', $originalText)));

        if (!$weight && !empty($cleanText)) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(g|ml|kg|l|oz)/iu', $cleanText, $matches)) {
                $weight = (float) str_replace(',', '.', $matches[1]);
            }
        }

        $measureText = $cleanText;
        if ($weight) {
            $measureText = preg_replace('/\b' . preg_quote($weight, '/') . '\s*(g|ml|kg|l|oz)\b/iu', '', $measureText);
            $measureText = preg_replace('/\((\s*)\)/', '', $measureText);
        }
        $measureText = trim(preg_replace('/^[^\w\d]+|[^\w\d]+$/u', '', $measureText));

        $qty = null;
        $unit = $measureText ?: null;

        if ($unit) {
            if (preg_match('/^(\d+(?:[.,]\d+)?(?:\s+\d+\/\d+)?|\d+\/\d+)\s+(.+)$/iu', $unit, $m)) {
                $qty = $m[1];
                $unit = Str::ucfirst($m[2]);
            }
        }

        $servings = null;
        $totalWeight = (float) ($product['product_quantity'] ?? $product['quantity'] ?? 0);
        if ($totalWeight > 0 && $weight > 0) {
            $servings = round($totalWeight / $weight, 1);
        }

        return [
            'weight' => $weight,
            'qty' => $qty,
            'unit' => $unit,
            'servings' => $servings,
        ];
    }

    protected function processTags(array $tags, bool $translate): ?string
    {
        if (empty($tags)) return null;
        $processed = [];
        foreach ($tags as $tag) {
            $cleanTag = preg_replace('/^[a-z]{2}:/', '', $tag);
            $cleanTag = str_replace('-', ' ', $cleanTag);

            if ($translate && isset($this->allergenMapPt[$tag])) {
                $processed[] = $this->allergenMapPt[$tag];
            } else {
                $processed[] = Str::title($cleanTag);
            }
        }
        return implode(', ', array_unique($processed));
    }

    protected function downloadNutritionImage(array $product, string $barcode): ?string
    {
        $images = $product['selected_images']['nutrition']['display'] ?? [];
        $url = $images['pt'] ?? $images['en'] ?? reset($images);
        if (empty($url) || !is_string($url)) return null;

        try {
            $contents = Http::timeout(10)->get($url)->body();
            $ext = pathinfo($url, PATHINFO_EXTENSION) ?: 'jpg';
            $path = "uploads/nutritional/{$barcode}_nutri_" . time() . ".{$ext}";
            Storage::disk('public')->put($path, $contents, 'public');
            return $path;
        } catch (\Exception $e) { return null; }
    }
}