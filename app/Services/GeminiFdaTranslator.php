<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiFdaTranslator
{
    protected $apiKey;
    
    // Mantendo o modelo 2.5 Flash que sua conta possui
    protected $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite-001:generateContent';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
    }

    public function translate(string $productName): ?string
    {
        if (empty($this->apiKey)) {
            Log::error("Gemini API Key não encontrada.");
            return null;
        }

        // Prompt reforçado com exemplos e instrução de COMPLETUDE
        $prompt = <<<EOT
Você é um especialista em rotulagem FDA.
Traduza o nome do produto abaixo do Português para o Inglês, gerando o "Statement of Identity" completo.

REGRAS:
1. NÃO traduza a MARCA (Ex: "Lacta", "Nestlé", "Trakinas").
2. Traduza o resto para o nome comum em inglês (Ex: "Wafer", "Cookies").
3. Mantenha o peso/volume no final.

EXEMPLOS (Use como guia):
- "Biscoito Recheado Trakinas Morango 140g" -> "Trakinas Strawberry Sandwich Cookies 140g"
- "Refrigerante Coca Cola 2L" -> "Coca Cola Soft Drink 2L"
- "Chocolate Barra Lacta Diamante Negro 90g" -> "Lacta Diamante Negro Chocolate Bar 90g"

AGORA TRADUZA (Retorne APENAS o nome final completo):
Entrada: "{$productName}"
Saída:
EOT;

        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.3, // Aumentei levemente para fluidez
                    'maxOutputTokens' => 500, // AUMENTEI DRASTICAMENTE para evitar cortes (era 60/100)
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                // Limpeza agressiva de prefixos que a IA possa inventar
                $cleanText = str_replace(['Saída:', 'Output:', 'Translation:', '"', '**'], '', $text);
                return trim($cleanText); 
            } else {
                Log::error("Erro Gemini API: " . $response->body());
                return null;
            }

        } catch (\Exception $e) {
            Log::error("Exceção Gemini: " . $e->getMessage());
            return null;
        }
    }
}