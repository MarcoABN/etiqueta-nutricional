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

    public $timeout = 300; 
    public $tries = 1; 

    public function __construct(public Product $product) {}

    public function handle(OllamaService $ollama, GeminiFdaTranslator $translator): void
    {
        $this->product->update(['ai_status' => 'processing']);

        try {
            $nutritionalData = [];
            
            // 1. Extração da Imagem (Visão)
            if ($this->product->image_nutritional) {
                $path = Storage::disk('public')->path($this->product->image_nutritional);
                if (file_exists($path)) {
                    $b64 = $this->optimizeImage($path);
                    $extractedData = $ollama->extractNutritionalData($b64, 180);
                    if ($extractedData) {
                        $nutritionalData = $extractedData;
                    }
                }
            }

            // 2. Aplicação de Defaults (Zeros Obrigatórios)
            $mandatoryFields = [
                'total_fat', 'total_fat_dv',
                'sat_fat', 'sat_fat_dv',
                'trans_fat', 'trans_fat_dv',
                'cholesterol', 'cholesterol_dv',
                'sodium', 'sodium_dv',
                'total_carb', 'total_carb_dv',
                'fiber', 'fiber_dv',
                'total_sugars', 
                'added_sugars', 'added_sugars_dv',
                'protein', 'protein_dv'
            ];

            foreach ($mandatoryFields as $field) {
                $nutritionalData[$field] = $nutritionalData[$field] ?? 0;
            }

            // 3. Tradução do Nome (Se necessário)
            if (empty($this->product->product_name_en)) {
                $translatedName = $translator->translate($this->product->product_name);
                if ($translatedName) {
                    $nutritionalData['product_name_en'] = $translatedName;
                }
            }

            // 4. Salvar
            if (!empty($nutritionalData)) {
                $this->product->update(array_merge($nutritionalData, [
                    'ai_status' => 'completed',
                    'last_ai_processed_at' => now(),
                    'ai_error_message' => null
                ]));
                Log::info("Produto {$this->product->id} processado com sucesso.");
            } else {
                throw new \Exception("Nenhum dado gerado.");
            }

        } catch (\Exception $e) {
            $this->product->update([
                'ai_status' => 'error',
                'ai_error_message' => $e->getMessage()
            ]);
            Log::error("Erro Job Produto {$this->product->id}: " . $e->getMessage());
        }
    }

    private function optimizeImage($path): string
    {
        list($width, $height, $type) = getimagesize($path);
        if ($width <= 1024) return base64_encode(file_get_contents($path));
        
        $newWidth = 1024;
        $newHeight = ($height / $width) * $newWidth;
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        
        switch ($type) {
            case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG: $source = imagecreatefrompng($path); break;
            case IMAGETYPE_WEBP: $source = imagecreatefromwebp($path); break;
            default: return base64_encode(file_get_contents($path));
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start();
        imagejpeg($thumb, null, 80);
        $data = ob_get_clean();
        imagedestroy($thumb);
        imagedestroy($source);
        return base64_encode($data);
    }
}