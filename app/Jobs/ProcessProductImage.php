<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\OllamaService;
use App\Services\GeminiFdaTranslator; // CLASSE DE TRADUÇÃO RESTAURADA
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // Aumentado para 10 minutos (Ollama pode ser lento)
    public $tries = 1; 

    public function __construct(public Product $product) {}

    // INJEÇÃO DE DEPENDÊNCIA: OLLAMA + TRADUTOR
    public function handle(OllamaService $ollama, GeminiFdaTranslator $translator): void
    {
        Log::info("Job Iniciado: Produto ID {$this->product->id}");
        $this->product->update(['ai_status' => 'processing']);

        $updates = []; // Array acumulador de atualizações
        $errors = [];

        try {
            // --- ETAPA 1: OCR / VISÃO (Tabela Nutricional) ---
            if ($this->product->image_nutritional) {
                try {
                    $path = Storage::disk('public')->path($this->product->image_nutritional);
                    
                    if (file_exists($path)) {
                        // Otimização sem perda de qualidade para texto pequeno
                        $b64 = $this->prepareImage($path);
                        
                        Log::info("Enviando imagem para Ollama...");
                        $nutritionalData = $ollama->extractNutritionalData($b64, 300);

                        if ($nutritionalData) {
                            $updates = array_merge($updates, $nutritionalData);
                            Log::info("Dados nutricionais extraídos com sucesso.");
                        } else {
                            $errors[] = "Ollama retornou vazio.";
                        }
                    } else {
                        $errors[] = "Arquivo de imagem não encontrado no disco.";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Erro OCR: " . $e->getMessage();
                    Log::error("Erro no processamento da imagem: " . $e->getMessage());
                }
            }

            // --- ETAPA 2: TRADUÇÃO DO NOME (Mantendo funcionalidade existente) ---
            if (empty($this->product->product_name_en)) {
                try {
                    Log::info("Iniciando tradução do nome...");
                    $translatedName = $translator->translate($this->product->product_name);
                    
                    if ($translatedName) {
                        $updates['product_name_en'] = $translatedName;
                        Log::info("Nome traduzido: $translatedName");
                    }
                } catch (\Exception $e) {
                    $errors[] = "Erro Tradução: " . $e->getMessage();
                    Log::error("Erro na tradução: " . $e->getMessage());
                }
            }

            // --- ETAPA 3: VALORES PADRÃO (Zeros Obrigatórios) ---
            // Só aplica defaults se tivermos extraído algo OU se já existirem dados parciais
            // Isso evita zerar tudo se o OCR falhar completamente.
            if (!empty($updates)) {
                $mandatoryFields = [
                    'total_fat', 'sat_fat', 'trans_fat', 
                    'cholesterol', 'sodium', 
                    'total_carb', 'fiber', 'total_sugars', 
                    'added_sugars', 'protein'
                ];

                foreach ($mandatoryFields as $field) {
                    // Se o campo veio do OCR, mantém. Se não, e não existe no updates, define 0.
                    if (!isset($updates[$field])) {
                        $updates[$field] = 0;
                    }
                }
            }

            // --- ETAPA 4: SALVAR ---
            if (!empty($updates)) {
                $updates['ai_status'] = 'completed';
                $updates['last_ai_processed_at'] = now();
                $updates['ai_error_message'] = empty($errors) ? null : implode(" | ", $errors);
                
                $this->product->update($updates);
                Log::info("Produto {$this->product->id} atualizado com sucesso.");
            } else {
                // Se chegou aqui, nada foi gerado (nem OCR, nem Tradução)
                $this->product->update([
                    'ai_status' => 'error',
                    'ai_error_message' => "Nenhum dado gerado. Erros: " . implode(" | ", $errors)
                ]);
            }

        } catch (\Exception $e) {
            // Catch global para falhas catastróficas
            $this->product->update([
                'ai_status' => 'error',
                'ai_error_message' => $e->getMessage()
            ]);
            Log::error("Falha Crítica Job Produto {$this->product->id}: " . $e->getMessage());
        }
    }

    private function prepareImage($path): string
    {
        // Se a imagem for pequena (recorte), envia direto para não perder nitidez
        list($width, $height) = getimagesize($path);
        
        if ($width <= 1500 && $height <= 1500) {
            return base64_encode(file_get_contents($path));
        }
        
        // Redimensionamento conservador apenas para fotos gigantescas
        $newWidth = 1500; 
        $newHeight = ($height / $width) * $newWidth;
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        
        $type = exif_imagetype($path);
        switch ($type) {
            case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG: $source = imagecreatefrompng($path); break;
            case IMAGETYPE_WEBP: $source = imagecreatefromwebp($path); break;
            default: return base64_encode(file_get_contents($path));
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start();
        imagejpeg($thumb, null, 90); // Alta qualidade
        $data = ob_get_clean();
        imagedestroy($thumb);
        imagedestroy($source);
        
        return base64_encode($data);
    }
}