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
            // Aumentado para 60s para garantir o 'cold start' da GPU
            $prompt = $this->getSystemPrompt($productName);
            $result = $this->ollama->completion($prompt, 60);
            
            return $this->cleanText($result);
        } catch (\Exception $e) {
            Log::error("Erro Tradução: " . $e->getMessage());
            return null;
        }
    }

    private function cleanText(?string $text): ?string
    {
        if (empty($text)) return null;
        $text = str_replace(['```json', '```'], '', $text);
        // Remove prefixos comuns de resposta de IA
        $text = preg_replace('/^(Output|Translation|Answer|Identity Statement|EN|English):\s*/i', '', $text);
        return trim($text, " \t\n\r\0\x0B\"'");
    }

    private function getSystemPrompt(string $productName): string
    {
        return <<<EOT
You are an expert in **FDA Food Labeling** and **Commercial Product Identity**.
Your task is to decode Brazilian ERP abbreviations and generate a compliant English Identity Statement.

### 1. DECODE TABLE (ERP -> ENGLISH CONCEPT):
- "SALG", "SALGADINHO" -> "Snack"
- "BISC", "BISCOITO" -> "Cookies" (Sweet) or "Crackers" (Savory) or "Wafer"
- "CHOC", "CHOCOLATE" -> "Chocolate"
- "PIR", "PIRULITO" -> "Lollipop"
- "DROPS", "BALA" -> "Hard Candy"
- "SABAO PO" -> "Powder Laundry Detergent"
- "SABAO LIQ" -> "Liquid Laundry Detergent"
- "AMAC", "AMACIANTE" -> "Fabric Softener"
- "DESINF" -> "Disinfectant"
- "SACO", "SACOLA" -> "Bag"
- "PLAST" -> "Plastic"
- "PRATO" -> "Plate"
- "DESC", "DESCARTAVEL" -> "Disposable"
- "BD" -> "Low Density (LDPE)"
- "PP" -> "Polypropylene"
- "SANF" -> "Gusseted"

### 2. RULES BY CATEGORY:
**A) FOOD (FDA Rules):**
- **Flavor:** Use "Flavored" for artificial flavors (e.g., "Presunto" -> "Ham Flavored", "Morango" -> "Strawberry Flavored").
- **Real Ingredient:** Use direct name only if it's a main ingredient (e.g., "Amendoim" -> "Peanuts").

**B) CLEANING/NON-FOOD:**
- **Scent:** Use "Scent" instead of "Flavor" (e.g., "Lavanda" -> "Lavender Scent").
- **Material:** List material before the item (e.g., "Prato Isopor" -> "Styrofoam Plate").

### 3. STRUCTURE:
[Brand] + [Feature/Flavor/Material] + [Common Name] + [Qty/Pack]

### 4. EXAMPLES FROM YOUR DATABASE:
- IN: "SALG SKINY PRESUNTO 60G"
  OUT: "Skiny Ham Flavored Snack 60g"

- IN: "SABAO PO OMO LAVAGEM PERFEITA 800G"
  OUT: "OMO Perfect Wash Powder Laundry Detergent 800g"

- IN: "SACO PLAST ZPP BD SANF 50X80CM 12MM KG"
  OUT: "Zipper Low Density Gusseted Plastic Bag 50x80cm 12mm"

- IN: "CHOC LACTA BIS OREO 100,8G"
  OUT: "Lacta Bis Oreo Flavored Wafer 100.8g"

- IN: "PRATO ISOPOR COPOBRAS BCO FUND 15CM"
  OUT: "Copobras White Deep Styrofoam Plate 15cm"

### INPUT PRODUCT:
"{$productName}"

### OUTPUT:
Return ONLY the final string.
EOT;
    }
}