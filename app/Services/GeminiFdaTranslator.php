<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiFdaTranslator
{
    protected $apiKey;
    protected $apiUrl = 'https://api.perplexity.ai/chat/completions';

    public function __construct()
    {
        // Agora buscamos a chave da Perplexity
        $this->apiKey = env('PERPLEXITY_API_KEY');
    }

    public function translate(string $productName): ?string
    {
        if (empty($this->apiKey)) {
            Log::error("PERPLEXITY_API_KEY não encontrada no .env");
            return null;
        }

        // Prompt de Sistema (Regras)
        $systemPrompt = <<<EOT
Você é um especialista em rotulagem FDA.
Traduza o nome do produto do Português para o Inglês, gerando o "Statement of Identity".

REGRAS:
1. NÃO traduza a MARCA (Ex: "Lacta", "Nestlé").
2. Traduza a descrição para o inglês comum (Ex: "Wafer", "Cookies").
3. Mantenha o peso/volume no final.

EXEMPLOS:
- "Biscoito Trakinas Morango 140g" -> "Trakinas Strawberry Sandwich Cookies 140g"
- "Refrigerante Coca Cola 2L" -> "Coca Cola Soft Drink 2L"

Retorne APENAS o nome final traduzido. Nada mais.
EOT;

        try {
            // A Perplexity usa o formato padrão OpenAI (Chat Completions)
            $response = Http::withOptions(['verify' => false])
                ->withToken($this->apiKey) // Bearer Token
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'model' => 'sonar', // Modelo rápido e eficiente da Perplexity (Llama 3.1 based)
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt
                        ],
                        [
                            'role' => 'user',
                            'content' => "Produto: \"{$productName}\""
                        ]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 200, // Perplexity usa max_tokens, não maxOutputTokens
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? null;
                
                // Limpeza final
                $cleanText = str_replace(['Saída:', 'Output:', '"', '.', 'Product:'], '', $text);
                return trim($cleanText); 
            } else {
                Log::error("Erro Perplexity API: " . $response->body());
                return null;
            }

        } catch (\Exception $e) {
            Log::error("Exceção Perplexity: " . $e->getMessage());
            return null;
        }
    }
}