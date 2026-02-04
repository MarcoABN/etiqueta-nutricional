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

    // AUMENTADO: Deve ser maior que o timeout do cURL no Service
    public $timeout = 1200; 
    
    // Evita que o Laravel tente rodar de novo se falhar por timeout
    public $tries = 1; 
    public $failOnTimeout = true;

    public function __construct(public Product $product) {}

    public function handle(OllamaService $ollama, GeminiFdaTranslator $translator): void
    {
        // 1. CHECAGEM DE IDEMPOTÊNCIA RIGOROSA
        // Se já completou, aborta. Se está processando e foi atualizado a menos de 10 min, aborta.
        if ($this->product->ai_status === 'completed') {
            Log::info("Job abortado: Produto {$this->product->id} já concluído.");
            return;
        }

        if ($this->product->ai_status === 'processing' && $this->product->updated_at->diffInMinutes(now()) < 10) {
             Log::info("Job abortado: Produto {$this->product->id} já está em processamento recente.");
             return;
        }

        Log::info("Job Iniciado (Tentativa única): Produto ID {$this->product->id}");
        
        // Marca como processando sem disparar eventos
        $this->product->updateQuietly(['ai_status' => 'processing', 'updated_at' => now()]);

        $updates = [];
        $errors = [];

        try {
            // --- ETAPA 1: OCR / VISÃO ---
            if ($this->product->image_nutritional) {
                try {
                    $path = Storage::disk('public')->path($this->product->image_nutritional);
                    
                    if (file_exists($path)) {
                        $b64 = $this->prepareImage($path);
                        
                        Log::info("Enviando imagem para Ollama (Aguardando até 600s)...");
                        
                        // Timeout de 600s (10 min) para o Ollama
                        $nutritionalData = $ollama->extractNutritionalData($b64, 600);

                        if ($nutritionalData) {
                            $updates = array_merge($updates, $nutritionalData);
                            Log::info("Dados nutricionais extraídos com sucesso.");
                        } else {
                            $errors[] = "Ollama retornou vazio/nulo.";
                        }
                    } else {
                        $errors[] = "Arquivo de imagem não encontrado.";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Erro Processamento Imagem: " . $e->getMessage();
                    Log::error("Erro Imagem: " . $e->getMessage());
                }
            }

            // --- ETAPA 2: TRADUÇÃO ---
            if (empty($this->product->product_name_en)) {
                try {
                    $translatedName = $translator->translate($this->product->product_name);
                    if ($translatedName) {
                        $updates['product_name_en'] = $translatedName;
                    }
                } catch (\Exception $e) {
                    // Tradução é secundária, não falha o job
                    Log::warning("Erro Tradução: " . $e->getMessage());
                }
            }

            // --- ETAPA 3: FINALIZAÇÃO ---
            if (!empty($updates)) {
                // Preenche defaults obrigatórios com 0 se não vieram
                $mandatory = ['calories', 'total_fat', 'total_carb', 'protein', 'sodium'];
                foreach ($mandatory as $field) {
                    if (!isset($updates[$field]) && !isset($this->product->$field)) {
                        $updates[$field] = '0';
                    }
                }

                $updates['ai_status'] = 'completed';
                $updates['last_ai_processed_at'] = now();
                $updates['ai_error_message'] = empty($errors) ? null : implode(" | ", $errors);
                
                $this->product->updateQuietly($updates);
                Log::info("Produto {$this->product->id} finalizado com sucesso.");
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
        // Limite de segurança: Redimensiona se for maior que 1600px para aliviar o POST
        list($width, $height) = getimagesize($path);
        
        // Se já estiver num tamanho bom (do crop), só retorna o base64
        if ($width <= 1600 && $height <= 1600) {
            return base64_encode(file_get_contents($path));
        }
        
        // Redimensiona mantendo proporção
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
        // Qualidade 85 reduz MUITO o tamanho do arquivo sem perder legibilidade OCR
        imagejpeg($thumb, null, 85); 
        $data = ob_get_clean();
        
        imagedestroy($thumb);
        imagedestroy($source);
        
        return base64_encode($data);
    }
}