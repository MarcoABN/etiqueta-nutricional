<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiFdaTranslator
{
    protected $ollama; // Serviço Local
    protected $googleKey;
    protected $perplexityKey;

    protected $googleUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite-001:generateContent';
    protected $perplexityUrl = 'https://api.perplexity.ai/chat/completions';

    // Injeção de dependência do OllamaService
    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
        $this->googleKey = env('GEMINI_API_KEY');
        $this->perplexityKey = env('PERPLEXITY_API_KEY');
    }

    public function translate(string $productName): ?string
    {
        // 1. TENTATIVA LOCAL (Prioridade Máxima - Custo Zero)
        // Se o serviço estiver online via Tailscale, usa ele.
        $localResult = $this->tryLocal($productName);
        if ($localResult) {
            return $localResult;
        }

        // 2. Tenta Google (Grátis, mas com limite de cota)
        if (!empty($this->googleKey)) {
            // Log apenas para monitorar se o local está falhando muito
            Log::info("Fallback para Google: $productName"); 
            $result = $this->tryGoogle($productName);
            if ($result) {
                return $result;
            }
        }

        // 3. Fallback Final: Perplexity (Pago/Cota)
        if (!empty($this->perplexityKey)) {
            Log::info("Fallback para Perplexity: $productName");
            return $this->tryPerplexity($productName);
        }

        return null;
    }

    // --- Lógica Local (Ollama) ---
    protected function tryLocal($productName)
    {
        $prompt = $this->getSystemPrompt($productName, 'local');
        
        // Timeout de apenas 5 segundos!
        // Se sua GPU estiver ocupada com imagem, isso vai falhar rápido e liberar o processo para o Google.
        $text = $this->ollama->completion($prompt, 15); 
        
        return $this->cleanText($text);
    }

    // --- Lógica do Google ---
    protected function tryGoogle($productName)
    {
        try {
            usleep(2000000); // 2s delay para evitar rate limit
            $prompt = $this->getSystemPrompt($productName, 'google');

            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->googleUrl}?key={$this->googleKey}", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 500]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                return $this->cleanText($text);
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // --- Lógica da Perplexity ---
    protected function tryPerplexity($productName)
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withToken($this->perplexityKey)
                ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
                ->post($this->perplexityUrl, [
                    'model' => 'sonar',
                    'messages' => [
                        ['role' => 'system', 'content' => $this->getSystemPrompt(null, 'perplexity')],
                        ['role' => 'user', 'content' => "Produto: \"{$productName}\""]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 200,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? null;
                return $this->cleanText($text);
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function cleanText($text)
    {
        if (!$text) return null;
        // Limpeza agressiva para garantir que só venha o nome
        return trim(str_replace(['Saída:', 'Output:', '"', 'Product:', 'Translation:'], '', $text));
    }

    private function getSystemPrompt($productName, $type)
    {
        $base = <<<EOT
Você é um especialista em rotulagem FDA. Traduza do Português para o Inglês.
REGRAS:
1. MANTENHA A MARCA (Ex: "Lacta").
2. TRADUZA a descrição (Ex: "Wafer").
3. NÃO USE BOLD (**), MARKDOWN, OR QUOTES.
4. MANTENHA o peso no final.
5. Retorne APENAS o texto traduzido final. Sem introduções.
EXEMPLO: "Biscoito Trakinas Morango 140g" -> "Trakinas Strawberry Sandwich Cookies 140g"
EOT;

        if ($type === 'local') {
             return $base . "\n\nProduto para traduzir: \"{$productName}\"";
        }
        
        if ($type === 'google') {
            return $base . "\n\nEntrada: \"{$productName}\"\nSaída:";
        }
        
        return $base;
    }
}