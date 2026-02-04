<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GeminiFdaTranslator
{
    protected OllamaService $ollama;

    // Injeção de dependência do serviço local
    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
    }

    /**
     * Gera o "Statement of Identity" (Nome FDA) para o produto.
     */
    public function translate(string $productName): ?string
    {
        try {
            $prompt = $this->getSystemPrompt($productName);
            
            // Timeout de 10 segundos é suficiente para processamento de texto puro (sem imagem)
            // Se demorar mais que isso, o OllamaService corta para não travar o PHP
            $result = $this->ollama->completion($prompt, 10);
            
            return $this->cleanText($result);
            
        } catch (\Exception $e) {
            Log::error("Erro na Tradução Local (FDA): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Limpa a resposta da LLM para garantir que salve apenas o nome.
     */
    private function cleanText(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        // Remove blocos de código markdown se houver
        $text = str_replace(['```json', '```'], '', $text);
        
        // Remove prefixos comuns de chat
        $text = str_replace(['Output:', 'Translation:', 'Identity Statement:', 'EN:', 'English:'], '', $text);
        
        // Remove aspas extras e espaços
        $text = trim($text, " \t\n\r\0\x0B\"'");

        return $text;
    }

    /**
     * Constrói o prompt baseado nas regras do CFR Title 21 (Sec 101.3 e 102.5)
     */
    private function getSystemPrompt(string $productName): string
    {
        return <<<EOT
You are an expert in FDA Food Labeling Regulations (21 CFR).
Your task is to convert a Portuguese commercial product name into a compliant **FDA Statement of Identity**.

### CRITICAL RULES (Based on 21 CFR 101.3):
1. **BRAND NAME**: Keep the brand name exactly as is at the beginning.
2. **COMMON NAME**: Identify what the food actually IS (e.g., "Biscoito Recheado" is "Sandwich Cookies", "Bebida Láctea" is "Dairy Beverage", "Salgadinho" is "Snack").
3. **FLAVORS**: 
   - "Sabor Morango" -> "Strawberry Flavored" (Artificial/Natural flavor).
   - "Com Morango" -> "Strawberry" (Real fruit).
   - If unsure, use "Flavored".
4. **QUANTITY/PACK**: Keep units (g, kg, ml, L) and pack counts (e.g., "12x1L", "Pack of 3") at the very end.

### STRUCTURE TARGET:
[Brand] + [Flavor/Feature] + [Common Name] + [Net Qty]

### EXAMPLES:
- IN: "Biscoito Trakinas Morango 140g"
  OUT: "Trakinas Strawberry Flavored Sandwich Cookies 140g"

- IN: "Leite Integral Itambé 1 Litro"
  OUT: "Itambé Whole Milk 1L"

- IN: "Achocolatado Nescau 2.0 400g"
  OUT: "Nescau 2.0 Chocolate Powder Drink Mix 400g"

- IN: "Refrigerante Coca-Cola Sem Açúcar 350ml"
  OUT: "Coca-Cola Zero Sugar Soda 350ml"

- IN: "Fardo Refrigerante Guaraná Antarctica 6x1,5L"
  OUT: "Guaraná Antarctica Soda 6x1.5L Pack"

### INPUT PRODUCT:
"{$productName}"

### OUTPUT INSTRUCTION:
Return **ONLY** the final English string. Do not explain. Do not use Markdown.
EOT;
    }
}