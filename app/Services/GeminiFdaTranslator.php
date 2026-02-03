<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeminiFdaTranslator
{
    protected $geminiKey;
    protected $perplexityKey;
    protected $geminiUrl;
    protected $perplexityUrl;

    public function __construct()
    {
        $this->geminiKey = env('GEMINI_API_KEY');
        $this->perplexityKey = env('PERPLEXITY_API_KEY');
        $this->geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent';
        $this->perplexityUrl = 'https://api.perplexity.ai/chat/completions';
    }

    /**
     * Tenta traduzir usando Gemini, com fallback para Perplexity.
     * Retorna NULL se ambos falharem.
     */
    public function translate(string $productName): ?string
    {
        // 1. Verifica Cache (evita gastar API com o mesmo produto)
        $cacheKey = 'trans_' . md5(strtoupper($productName));
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $translated = null;

        // 2. Tenta Google Gemini Primeiro
        try {
            $translated = $this->tryGoogle($productName);
        } catch (\Exception $e) {
            Log::warning("Gemini falhou: " . $e->getMessage());
        }

        // 3. Se Gemini falhou (Cota ou Erro), tenta Perplexity
        if (empty($translated)) {
            try {
                // Pequeno delay para evitar rate limit se estivermos num loop rápido
                usleep(200000); // 0.2s
                $translated = $this->tryPerplexity($productName);
            } catch (\Exception $e) {
                Log::error("Perplexity falhou: " . $e->getMessage());
            }
        }

        // 4. Processamento Final e Cache
        if ($translated) {
            // AQUI ESTÁ A CORREÇÃO: Limpa qualquer lixo (**texto**) antes de salvar
            $finalText = $this->sanitizeOutput($translated);

            // Salva no cache por 30 dias
            Cache::put($cacheKey, $finalText, now()->addDays(30));
            
            return $finalText;
        }

        return null;
    }

    /**
     * Limpa formatação Markdown e caracteres indesejados
     */
    private function sanitizeOutput(string $text): string
    {
        // 1. Remove asteriscos (** ou *), crases (`) e aspas simples/duplas das pontas
        $clean = str_replace(['**', '*', '`'], '', $text);
        
        // 2. Remove aspas do início e fim se sobrarem
        $clean = trim($clean, '"\'');

        // 3. Remove prefixos comuns que as IAs gostam de colocar
        $prefixes = [
            'Translation:', 'Product:', 'English Name:', 'Name:', 
            'Tradução:', 'Output:', 'English:'
        ];
        
        foreach ($prefixes as $prefix) {
            if (stripos($clean, $prefix) === 0) {
                $clean = substr($clean, strlen($prefix));
            }
        }

        // 4. Limpa espaços extras e quebras de linha
        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    protected function tryGoogle($productName)
    {
        if (empty($this->geminiKey)) return null;

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post($this->geminiUrl . '?key=' . $this->geminiKey, [
                'contents' => [
                    ['parts' => [['text' => $this->getSystemPrompt($productName)]]]
                ],
                'generationConfig' => [
                    'temperature' => 0.1, // Temperatura baixa para ser mais literal
                    'maxOutputTokens' => 60,
                ]
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        }

        // Se der erro de cota (429), lançamos exceção para o catch pegar e ir pro Perplexity
        if ($response->status() === 429) {
            throw new \Exception("Cota excedida (429)");
        }

        return null;
    }

    protected function tryPerplexity($productName)
    {
        if (empty($this->perplexityKey)) return null;

        $response = Http::withToken($this->perplexityKey)
            ->post($this->perplexityUrl, [
                'model' => 'llama-3.1-sonar-small-128k-online', // Modelo rápido e barato
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a precise translator for FDA food labels. Translate the product name from Portuguese to English. OUTPUT ONLY THE TRANSLATED NAME. NO MARKDOWN. NO BOLD. NO EXPLANATIONS.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Translate: \"{$productName}\""
                    ]
                ],
                'temperature' => 0.1
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? null;
        }

        return null;
    }

    /**
     * Prompt Unificado e Reforçado
     */
    protected function getSystemPrompt($productName)
    {
        return <<<TEXT
        Translate this Brazilian food product name to English for an FDA export label.
        
        Input: "{$productName}"
        
        Strict Rules:
        1. Return ONLY the translated name. Nothing else.
        2. Do NOT use markdown (no **bold**, no *italics*).
        3. Do NOT use quotes around the result.
        4. Keep numbers and metric units (g, kg, ml) as is.
        5. Keep brand names (like Arcor, Dori, Nestlé) original.
        
        Example Input: "Bala 7 Belo Framboesa 600g"
        Example Output: 7 Belo Raspberry Chewy Candy 600g
        TEXT;
    }
}