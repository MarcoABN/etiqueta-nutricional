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

    public $timeout = 900; // 15 minutos (Ollama Vision é pesado)
    public $tries = 1; 

    public function __construct(public Product $product) {}

    public function handle(OllamaService $ollama, GeminiFdaTranslator $translator): void
    {
        // 1. Prevenção de Loop: Se já estiver completo ou processando há pouco tempo, para.
        if ($this->product->ai_status === 'completed' || 
           ($this->product->ai_status === 'processing' && $this->product->updated_at->diffInMinutes(now()) < 2)) {
            Log::info("Job ignorado para evitar duplicidade: {$this->product->id}");
            return;
        }

        Log::info("Job Iniciado: Produto ID {$this->product->id}");
        
        // updateQuietly: Atualiza sem disparar eventos (evita loops de Observer)
        $this->product->updateQuietly(['ai_status' => 'processing']);

        $updates = [];
        $errors = [];

        try {
            // --- ETAPA 1: OCR / VISÃO ---
            if ($this->product->image_nutritional) {
                try {
                    $path = Storage::disk('public')->path($this->product->image_nutritional);
                    
                    if (file_exists($path)) {
                        // Prepara a imagem preservando a qualidade do Crop
                        $b64 = $this->prepareImage($path);
                        
                        Log::info("Enviando imagem para Ollama...");
                        // Timeout aumentado no service também
                        $nutritionalData = $ollama->extractNutritionalData($b64, 400);

                        if ($nutritionalData) {
                            $updates = array_merge($updates, $nutritionalData);
                            Log::info("Dados nutricionais extraídos com sucesso.");
                        } else {
                            $errors[] = "Ollama retornou vazio ou falhou.";
                        }
                    } else {
                        $errors[] = "Arquivo não encontrado: $path";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Erro OCR: " . $e->getMessage();
                    Log::error("Erro Imagem: " . $e->getMessage());
                }
            }

            // --- ETAPA 2: TRADUÇÃO (Mantida Original) ---
            if (empty($this->product->product_name_en)) {
                try {
                    $translatedName = $translator->translate($this->product->product_name);
                    if ($translatedName) {
                        $updates['product_name_en'] = $translatedName;
                    }
                } catch (\Exception $e) {
                    Log::warning("Erro Tradução: " . $e->getMessage());
                    // Não falha o job inteiro por causa da tradução
                }
            }

            // --- ETAPA 3: SALVAR ---
            if (!empty($updates)) {
                // Aplica zeros padrão apenas se necessário
                $mandatory = ['total_fat', 'sat_fat', 'trans_fat', 'sodium', 'total_carb', 'protein'];
                foreach ($mandatory as $field) {
                    if (!isset($updates[$field]) && !isset($this->product->$field)) {
                        $updates[$field] = '0';
                    }
                }

                $updates['ai_status'] = 'completed';
                $updates['last_ai_processed_at'] = now();
                $updates['ai_error_message'] = empty($errors) ? null : implode(" | ", $errors);
                
                $this->product->updateQuietly($updates); // Quietly aqui também
                Log::info("Produto {$this->product->id} finalizado com sucesso.");
            } else {
                $this->product->updateQuietly([
                    'ai_status' => 'error',
                    'ai_error_message' => "Nenhum dado gerado. " . implode(" | ", $errors)
                ]);
            }

        } catch (\Exception $e) {
            $this->product->updateQuietly([
                'ai_status' => 'error', 
                'ai_error_message' => $e->getMessage()
            ]);
            Log::error("Falha Fatal Job: " . $e->getMessage());
        }
    }

    private function prepareImage($path): string
    {
        // Se a imagem vier do Crop (ex: < 1MB ou dimensões controladas), 
        // NÃO redimensione. O redimensionamento do PHP (GD) pode borrar letras miúdas.
        
        list($width, $height) = getimagesize($path);
        $filesize = filesize($path);

        // Limite seguro: 1800px ou 2MB. Se for menor, manda direto.
        if ($width <= 1800 && $height <= 1800 && $filesize < 2 * 1024 * 1024) {
            return base64_encode(file_get_contents($path));
        }
        
        // Se for muito grande (foto crua da câmera), redimensiona
        $newWidth = 1600; // Aumentei de 1500 para 1600 para melhorar leitura
        $newHeight = ($height / $width) * $newWidth;
        
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        
        // Mantém fundo branco para transparências (ajuda OCR)
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
        imagejpeg($thumb, null, 95); // Qualidade 95 (Alta)
        $data = ob_get_clean();
        
        imagedestroy($thumb);
        imagedestroy($source);
        
        return base64_encode($data);
    }
}