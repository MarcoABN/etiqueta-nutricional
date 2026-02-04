<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GeminiFdaTranslator
{
    protected OllamaService $ollama;

    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
    }

    public function translate(string $productName): ?string
    {
        try {
            $prompt = $this->getSystemPrompt($productName);
            // Timeout de 60s para garantir
            $result = $this->ollama->completion($prompt, 60);
            
            return $this->cleanText($result);
        } catch (\Exception $e) {
            Log::error("Erro Tradução: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Limpeza robusta para extrair apenas o nome final e evitar estouro de campo
     */
    private function cleanText(?string $text): ?string
    {
        if (empty($text)) return null;

        // 1. Remove blocos de código e formatação
        $text = str_replace(['```json', '```', '**'], '', $text);

        // 2. Busca pelo marcador de saída (Se a IA explicar antes)
        if (preg_match('/(Final Output|Output|Result|OUT|SAÍDA|Translation)[:\s*-]*(.*)$/is', $text, $matches)) {
            $text = trim($matches[2]);
        }

        // 3. Fallback: Se ainda houver várias linhas, pega a última não vazia
        $lines = array_filter(explode("\n", $text), fn($l) => !empty(trim($l)));
        if (count($lines) > 0) {
            $text = trim(end($lines));
        }

        // 4. Limpeza final
        $text = trim($text, " \t\n\r\0\x0B\"'*");

        // 5. TRAVA DE SEGURANÇA: Corta em 255 chars para evitar erro SQL
        return substr($text, 0, 255);
    }

    private function getSystemPrompt(string $productName): string
    {
        return <<<EOT
You are an expert in FDA Food Labeling and Commercial Product Identity.
Task: Decode Brazilian ERP abbreviations and generate a compliant English Identity Statement.

### 1. COMPREHENSIVE DICTIONARY (ERP -> ENGLISH):
**Common Prefixes:**
- "SALG", "SALGADINHO" -> "Snack"
- "BISC", "BISCOITO" -> "Cookies" (Sweet) or "Crackers" (Savory) or "Wafer"
- "CHOC", "CHOCOLATE" -> "Chocolate"
- "PIR", "PIRULITO" -> "Lollipop"
- "DROPS", "BALA" -> "Hard Candy"
- "REFR", "REFRIG" -> "Soda"
- "BEB", "BEBIDA" -> "Beverage"

**Cleaning & Non-Food:**
- "SABAO PO" -> "Powder Laundry Detergent"
- "SABAO LIQ" -> "Liquid Laundry Detergent"
- "AMAC", "AMACIANTE" -> "Fabric Softener"
- "DESINF" -> "Disinfectant"
- "SHAMP" -> "Shampoo"
- "COND" -> "Conditioner"

**Packaging & Materials:**
- "SACO", "SACOLA" -> "Bag"
- "PLAST" -> "Plastic"
- "PRATO" -> "Plate"
- "DESC", "DESCARTAVEL" -> "Disposable"
- "BD" -> "Low Density (LDPE)"
- "PP" -> "Polypropylene"
- "SANF" -> "Gusseted"
- "ZPP", "ZIP" -> "Zipper"

### 2. RULES:
A. **Format:** [Brand] + [Flavor/Material/Feature] + [Common Name] + [Qty]
B. **Flavor (Food):** Use "Flavored" for artificial flavors (e.g., "Presunto" -> "Ham Flavored").
C. **Scent (Cleaning):** Use "Scent" for cleaning products (e.g., "Coco" -> "Coconut Scent").
D. **Material (Pkg):** Material comes before the item name (e.g., "Styrofoam Plate").

### 3. EXAMPLES:
IN: "SALG SKINY PRESUNTO 60G"
OUT: "Skiny Ham Flavored Snack 60g"

IN: "SACO PLAST ZPP BD SANF 50X80CM 12MM KG"
OUT: "Zipper Low Density Gusseted Plastic Bag 50x80cm 12mm"

IN: "SABAO LIQ OMO COCO 900ML"
OUT: "OMO Coconut Scent Liquid Laundry Detergent 900ml"

### INPUT PRODUCT:
"{$productName}"

### INSTRUCTION:
Output ONLY the final string. Do not explain logic.
EOT;
    }
}