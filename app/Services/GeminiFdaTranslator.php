<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiFdaTranslator
{
    protected $googleKey;
    protected $perplexityKey;

    // MELHOR OPÇÃO GOOGLE: Versão "Lite" (geralmente tem cota maior que a Pro/Flash padrão)
    protected $googleUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite-001:generateContent';

    // PERPLEXITY (Fallback rápido e confiável)
    protected $perplexityUrl = 'https://api.perplexity.ai/chat/completions';

    public function __construct()
    {
        $this->googleKey = env('GEMINI_API_KEY');
        $this->perplexityKey = env('PERPLEXITY_API_KEY');
    }

    public function translate(string $productName): ?string
    {
        // 1. Tenta Google (Grátis)
        // Se a chave existir, tentamos economizar usando o Google primeiro
        if (!empty($this->googleKey)) {
            $result = $this->tryGoogle($productName);
            if ($result)
                return $result;
        }

        // 2. Fallback: Perplexity
        // Se Google falhar (cota ou erro), ou se não tiver chave Google, usa Perplexity
        if (!empty($this->perplexityKey)) {
            // Só loga se realmente tiver tentado o Google antes e falhado
            if (!empty($this->googleKey)) {
                Log::info("Google falhou/esgotou. Usando Perplexity para: $productName");
            }
            return $this->tryPerplexity($productName);
        }

        return null;
    }

    // --- Lógica do Google ---
    protected function tryGoogle($productName)
    {
        try {
            // Delay obrigatório para o Free Tier não bloquear por RPM (Requests Per Minute)
            // 4 segundos = 15 requisições por minuto (limite seguro)
            usleep(4000000);

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

            return null; // Qualquer erro (429, 500) retorna null para ativar o fallback

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
                    'model' => 'sonar', // Modelo rápido (Llama 3.1 based)
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
            Log::error("Erro Perplexity: " . $e->getMessage());
            return null;
        }
    }

    private function cleanText($text)
    {
        if (!$text)
            return null;
        return trim(str_replace(['Saída:', 'Output:', '"', '.', 'Product:'], '', $text));
    }

    private function getSystemPrompt($productName, $type)
    {
        $base = <<<EOT
Você é um especialista em rotulagem FDA. Traduza do Português para o Inglês.
REGRAS:
1. MANTENHA A MARCA (Ex: "Lacta").
2. TRADUZA a descrição (Ex: "Wafer").
3. MANTENHA o peso no final.
EXEMPLOS:
- "Biscoito Trakinas Morango 140g" -> "Trakinas Strawberry Sandwich Cookies 140g"
EOT;

        if ($type === 'google') {
            return $base . "\n\nAGORA TRADUZA APENAS O NOME FINAL:\nEntrada: \"{$productName}\"\nSaída:";
        }
        return $base . "\nRetorne APENAS o nome final traduzido.";
    }
}