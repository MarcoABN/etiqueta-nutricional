<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\OllamaService;
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

    public function handle(OllamaService $ollama): void
    {
        $this->product->update(['ai_status' => 'processing']);
        Log::info("Iniciando processamento AI para produto: {$this->product->id}");

        try {
            $nutritionalData = [];
            
            // 1. Extração da Imagem
            if ($this->product->image_nutritional) {
                $path = Storage::disk('public')->path($this->product->image_nutritional);
                
                if (file_exists($path)) {
                    // Prepara a imagem preservando qualidade
                    $b64 = $this->prepareImageForOllama($path);
                    
                    // Chama o serviço
                    $extractedData = $ollama->extractNutritionalData($b64, 240); // Aumentei timeout interno
                    
                    if ($extractedData) {
                        $nutritionalData = $extractedData;
                    }
                } else {
                    throw new \Exception("Arquivo de imagem não encontrado: $path");
                }
            }

            // 2. Validação Mínima (Se tudo veio zero, algo deu errado)
            if (empty($nutritionalData) || (isset($nutritionalData['calories']) && $nutritionalData['calories'] === 0 && $nutritionalData['sodium'] === 0)) {
                throw new \Exception("IA retornou dados vazios ou zerados. Verifique a qualidade do recorte.");
            }

            // 3. Salvar
            $this->product->update(array_merge($nutritionalData, [
                'ai_status' => 'completed',
                'last_ai_processed_at' => now(),
                'ai_error_message' => null
            ]));
            
            Log::info("Sucesso Produto {$this->product->id}");

        } catch (\Exception $e) {
            $this->product->update([
                'ai_status' => 'error',
                'ai_error_message' => substr($e->getMessage(), 0, 250)
            ]);
            Log::error("Erro Job Produto {$this->product->id}: " . $e->getMessage());
        }
    }

    private function prepareImageForOllama($path): string
    {
        // Se a imagem for pequena (resultado de crop), NÃO REDIMENSIONAR.
        // Apenas converter para base64 direto para manter a nitidez dos textos pequenos.
        list($width, $height) = getimagesize($path);
        
        // Se for muito grande (foto crua de 4000px), limitamos.
        // Se for menor que 1500px, mandamos original (provavelmente é o crop).
        if ($width <= 1500 && $height <= 1500) {
            $data = file_get_contents($path);
            return base64_encode($data);
        }
        
        // Lógica de redimensionamento apenas para imagens gigantescas
        $newWidth = 1500; // Aumentei de 1024 para 1500 para ler letras miúdas
        $newHeight = ($height / $width) * $newWidth;
        
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        
        // Detecta tipo
        $type = exif_imagetype($path);
        switch ($type) {
            case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG: $source = imagecreatefrompng($path); break;
            case IMAGETYPE_WEBP: $source = imagecreatefromwebp($path); break;
            default: return base64_encode(file_get_contents($path));
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        ob_start();
        // Qualidade 90 (antes era 80) para garantir leitura de texto pequeno
        imagejpeg($thumb, null, 90); 
        $data = ob_get_clean();
        
        imagedestroy($thumb);
        imagedestroy($source);
        
        return base64_encode($data);
    }
}