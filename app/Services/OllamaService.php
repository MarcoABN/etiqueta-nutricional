<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $visionModel;

    public function __construct()
    {
        $this->host = rtrim(env('OLLAMA_HOST', 'http://100.89.133.69:11434'), '/');
        $this->visionModel = env('OLLAMA_VISION_MODEL', 'qwen3-vl:8b'); 
    }

    public function extractNutritionalData(string $base64Image, int $timeoutSeconds = 600): ?array
    {
        // Prompt Expandido para cobrir %VD e Micronutrientes
        $prompt = <<<EOT
Analise esta Tabela Nutricional completa.
Objetivo: Extrair dados numéricos, incluindo Porção, Macronutrientes, %VD (Valores Diários) e Vitaminas/Minerais.

REGRAS DE EXTRAÇÃO:
1. **Identifique a Porção**: Separe o peso (ex: "30g") da medida caseira (ex: "1 colher").
2. **Colunas**: Geralmente há uma coluna de "Quantidade" e uma de "%VD" (ou %DV). Extraia ambas.
3. **Valores Zero**: Se estiver escrito "Zero", "Não contém", ou for um traço "-", retorne "0".
4. **Micronutrientes**: Procure no rodapé ou na lista por vitaminas (Vitamina A, C, Ferro, Cálcio, etc).

RETORNE APENAS ESTE JSON (Preencha com null ou "0" se não encontrar):
{
  "serving_weight": "ex: 30g",
  "serving_size_quantity": "ex: 1.5",
  "serving_size_unit": "ex: xícara",
  "servings_per_container": "ex: aprox 5",
  
  "calories": "numero",
  
  "total_carb": "numero",
  "total_carb_dv": "numero (%VD)",
  
  "total_sugars": "numero",
  "added_sugars": "numero",
  "added_sugars_dv": "numero (%VD)",
  "sugar_alcohol": "numero (polióis)",

  "protein": "numero",
  "protein_dv": "numero (%VD)",

  "total_fat": "numero",
  "total_fat_dv": "numero (%VD)",
  "sat_fat": "numero",
  "sat_fat_dv": "numero (%VD)",
  "trans_fat": "numero",
  "trans_fat_dv": "numero (%VD)",
  "poly_fat": "numero",
  "mono_fat": "numero",

  "fiber": "numero",
  "fiber_dv": "numero (%VD)",
  
  "sodium": "numero",
  "sodium_dv": "numero (%VD)",
  
  "cholesterol": "numero",
  "cholesterol_dv": "numero (%VD)",

  "vitamin_d": "numero",
  "calcium": "numero",
  "iron": "numero",
  "potassium": "numero",
  "vitamin_a": "numero",
  "vitamin_c": "numero",
  "vitamin_e": "numero",
  "zinc": "numero",
  "magnesium": "numero"
}
EOT;

        $response = $this->queryVision($this->visionModel, $prompt, $base64Image, $timeoutSeconds);

        if (!$response) return null;

        $jsonData = $this->robustJsonDecode($response);
        
        if (!$jsonData) {
            Log::error("Ollama: JSON inválido. Raw: " . substr($response, 0, 150));
            return null;
        }

        return $this->sanitizeData($jsonData);
    }

    private function queryVision(string $model, string $prompt, string $image, int $timeout): ?string
    {
        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(5)
                ->post("{$this->host}/api/chat", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt, 'images' => [$image]]
                    ],
                    'stream' => false,
                    'format' => 'json',
                    'options' => [
                        'temperature' => 0.0, // Precisão máxima, sem criatividade
                        'num_ctx' => 4096,    // Contexto alto para ler tabelas grandes
                    ]
                ]);

            if ($response->successful()) {
                return $response->json('message.content');
            }
            
            Log::error("Ollama API Erro: {$response->status()} - {$response->body()}");
            return null;

        } catch (\Exception $e) {
            Log::error("Ollama Conexão Falhou: " . $e->getMessage());
            return null;
        }
    }

    private function robustJsonDecode(string $input): ?array
    {
        // Limpa Markdown ```json ... ```
        $clean = preg_replace('/```(?:json)?/i', '', $input);
        $clean = str_replace(['```', '`'], '', $clean);
        
        // Garante que pegamos apenas o objeto JSON {}
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $clean = $matches[0];
        }
        
        // Remove vírgulas extras no final de objetos/arrays (erro comum de LLMs)
        $clean = preg_replace('/,\s*}/', '}', $clean);
        $clean = preg_replace('/,\s*]/', ']', $clean);

        return json_decode($clean, true);
    }

    private function sanitizeData(array $data): array
    {
        // Função auxiliar para limpar números (remove 'g', 'mg', '%', etc)
        // Mas mantém o valor como string para salvar no banco (conforme migration)
        $cleanNum = function($key) use ($data) {
            if (!isset($data[$key])) return null; // Retorna null se não existir, para o banco aceitar
            
            $raw = (string)$data[$key];
            // Remove tudo exceto números, pontos, vírgulas e traços
            $val = preg_replace('/[^0-9,\.-]/', '', $raw);
            
            // Troca vírgula por ponto para padronização decimal
            $val = str_replace(',', '.', $val);
            
            // Se ficou vazio ou é apenas um ponto, retorna null ou '0'
            if ($val === '' || $val === '.') return null;
            
            return $val;
        };

        // Textos simples (sem limpeza numérica estrita)
        $cleanText = fn($key) => isset($data[$key]) ? trim((string)$data[$key]) : null;

        return [
            // --- IDENTIFICAÇÃO DA PORÇÃO ---
            'serving_weight'        => $cleanText('serving_weight'),
            'serving_size_quantity' => $cleanText('serving_size_quantity'),
            'serving_size_unit'     => $cleanText('serving_size_unit'),
            'servings_per_container'=> $cleanText('servings_per_container'),
            
            // --- MACROS PRINCIPAIS ---
            'calories'          => $cleanNum('calories'),
            
            'total_carb'        => $cleanNum('total_carb'),
            'total_carb_dv'     => $cleanNum('total_carb_dv'),
            
            'total_sugars'      => $cleanNum('total_sugars'),
            'added_sugars'      => $cleanNum('added_sugars'),
            'added_sugars_dv'   => $cleanNum('added_sugars_dv'),
            'sugar_alcohol'     => $cleanNum('sugar_alcohol'), // Polióis

            'protein'           => $cleanNum('protein'),
            'protein_dv'        => $cleanNum('protein_dv'),

            // --- GORDURAS ---
            'total_fat'         => $cleanNum('total_fat'),
            'total_fat_dv'      => $cleanNum('total_fat_dv'),
            'sat_fat'           => $cleanNum('sat_fat'),
            'sat_fat_dv'        => $cleanNum('sat_fat_dv'),
            'trans_fat'         => $cleanNum('trans_fat'),
            'trans_fat_dv'      => $cleanNum('trans_fat_dv'),
            'poly_fat'          => $cleanNum('poly_fat'),
            'mono_fat'          => $cleanNum('mono_fat'),

            // --- OUTROS ---
            'fiber'             => $cleanNum('fiber'),
            'fiber_dv'          => $cleanNum('fiber_dv'),
            
            'sodium'            => $cleanNum('sodium'),
            'sodium_dv'         => $cleanNum('sodium_dv'),
            
            'cholesterol'       => $cleanNum('cholesterol'),
            'cholesterol_dv'    => $cleanNum('cholesterol_dv'),

            // --- MICRONUTRIENTES (VITAMINAS/MINERAIS) ---
            // Adicionei os mais comuns. Se a IA achar, ela preenche.
            'vitamin_d'         => $cleanNum('vitamin_d'),
            'calcium'           => $cleanNum('calcium'),
            'iron'              => $cleanNum('iron'),
            'potassium'         => $cleanNum('potassio'), // IA as vezes usa PT/EN
            'vitamin_a'         => $cleanNum('vitamin_a'),
            'vitamin_c'         => $cleanNum('vitamin_c'),
            'vitamin_e'         => $cleanNum('vitamin_e'),
            'zinc'              => $cleanNum('zinc'),
            'magnesium'         => $cleanNum('magnesium'),
            // Adicione outros aqui se quiser forçar a IA a buscar (ex: 'vitamin_b12')
        ];
    }
}