<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\OllamaService;
use App\Services\GeminiFdaTranslator;
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

    // Timeout PHP maior que o do cURL para evitar morte súbita do processo
    public $timeout = 1200; 
    public $tries = 1; 

    public function __construct(public Product $product) {}

    public function handle(OllamaService $ollama, GeminiFdaTranslator $translator): void
    {
        // 1. Refresh Obrigatório: Pega o estado REAL do banco, ignorando cache da fila
        $this->product->refresh();

        // 2. Verificação de Corrida (Race Condition)
        if ($this->product->ai_status === 'completed') {
            Log::info("JOB ABORTADO: Produto {$this->product->id} já foi concluído por outro processo.");
            return;
        }

        // Se já está processando e foi atualizado há menos de 5 minutos, é duplicidade.
        if ($this->product->ai_status === 'processing' && $this->product->updated_at->diffInMinutes(now()) < 5) {
             Log::info("JOB ABORTADO: Produto {$this->product->id} já está sendo processado.");
             return;
        }

        Log::info("JOB INICIADO (Único): Produto ID {$this->product->id}");
        
        // updateQuietly: Atualiza status sem disparar novos eventos
        $this->product->updateQuietly(['ai_status' => 'processing', 'updated_at' => now()]);

        $updates = [];
        $errors = [];

        try {
            // --- ETAPA 1: VISÃO COMPUTACIONAL (Ollama) ---
            if ($this->product->image_nutritional) {
                try {
                    $path = Storage::disk('public')->path($this->product->image_nutritional);
                    
                    if (file_exists($path)) {
                        $b64 = $this->prepareImage($path);
                        
                        Log::info("Enviando para Ollama (Timeout 600s)...");
                        // Timeout de 10 minutos exclusivo para o Ollama
                        $nutritionalData = $ollama->extractNutritionalData($b64, 600);

                        if ($nutritionalData) {
                            $updates = array_merge($updates, $nutritionalData);
                            Log::info("Ollama retornou dados com sucesso.");
                        } else {
                            $errors[] = "Ollama retornou vazio.";
                        }
                    } else {
                        $errors[] = "Imagem não encontrada no disco.";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Erro Imagem: " . $e->getMessage();
                    Log::error("Erro Processamento Imagem: " . $e->getMessage());
                }
            }

            // --- ETAPA 2: TRADUÇÃO (Gemini) ---
            if (empty($this->product->product_name_en)) {
                try {
                    $translatedName = $translator->translate($this->product->product_name);
                    if ($translatedName) {
                        $updates['product_name_en'] = $translatedName;
                    }
                } catch (\Exception $e) {
                    Log::warning("Erro Tradução (não fatal): " . $e->getMessage());
                }
            }

            // --- ETAPA 3: SALVAR ---
            if (!empty($updates)) {
                // Preenche campos obrigatórios com 0 caso a IA não tenha achado
                $mandatory = ['calories', 'total_fat', 'total_carb', 'protein', 'sodium', 'total_sugars'];
                foreach ($mandatory as $field) {
                    if (!isset($updates[$field]) && !isset($this->product->$field)) {
                        $updates[$field] = '0';
                    }
                }

                $updates['ai_status'] = 'completed';
                $updates['last_ai_processed_at'] = now();
                $updates['ai_error_message'] = empty($errors) ? null : implode(" | ", $errors);
                
                $this->product->updateQuietly($updates);
                Log::info("Produto {$this->product->id} FINALIZADO com sucesso.");
            } else {
                $this->product->updateQuietly([
                    'ai_status' => 'error',
                    'ai_error_message' => "Nenhum dado extraído. " . implode(" | ", $errors)
                ]);
            }

        } catch (\Exception $e) {
            $this->product->updateQuietly([
                'ai_status' => 'error', 
                'ai_error_message' => "Erro Fatal: " . $e->getMessage()
            ]);
            Log::error("Falha Fatal Job: " . $e->getMessage());
        }
    }

    private function prepareImage($path): string
    {
        list($width, $height) = getimagesize($path);
        
        // Se a imagem já é pequena (do crop), não mexe
        if ($width <= 1600 && $height <= 1600) {
            return base64_encode(file_get_contents($path));
        }
        
        // Redimensiona apenas imagens gigantes para não estourar payload
        $newWidth = 1600; 
        $newHeight = ($height / $width) * $newWidth;
        
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $white);

        $type = exif_imagetype($path);
        switch ($type) {
            case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG: $source = imagecreatefrompng($path); break;
            case IMAGETYPE_WEBP: $source = imagecreatefromwebp($path); break;
            default: return base64_encode(file_get_contents($path));
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        ob_start();
        imagejpeg($thumb, null, 85); // Compressão leve
        $data = ob_get_clean();
        
        imagedestroy($thumb);
        imagedestroy($source);
        
        return base64_encode($data);
    }
}