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

    // Timeout do Job no Laravel (Dá 5 minutos para o processo todo)
    public $timeout = 300; 
    public $tries = 1; 

    public function __construct(public Product $product) {}

    public function handle(OllamaService $ollama): void
    {
        $this->product->update(['ai_status' => 'processing']);

        try {
            if (!$this->product->image_nutritional) {
                throw new \Exception("Imagem não encontrada.");
            }

            $path = Storage::disk('public')->path($this->product->image_nutritional);
            
            // --- OTIMIZAÇÃO DE IMAGEM (NOVO) ---
            // Reduz para max 1000px de largura mantendo proporção
            // Isso reduz o payload de ~4MB para ~200KB, acelerando o upload via VPN
            $b64 = $this->optimizeImage($path);
            
            // Envia para o Ollama com timeout generoso de 180s (3 min)
            // Passamos o timeout explicitamente aqui
            $data = $ollama->extractNutritionalData($b64, 180);

            if (!$data) {
                throw new \Exception("Ollama retornou vazio.");
            }

            $this->product->update(array_merge($data, [
                'ai_status' => 'completed',
                'last_ai_processed_at' => now(),
                'ai_error_message' => null
            ]));

            Log::info("Produto {$this->product->id} processado com sucesso.");

        } catch (\Exception $e) {
            $this->product->update([
                'ai_status' => 'error',
                'ai_error_message' => $e->getMessage()
            ]);
            Log::error("Falha Produto {$this->product->id}: " . $e->getMessage());
        }
    }

    /**
     * Redimensiona a imagem para otimizar o tráfego de rede via Tailscale
     */
    private function optimizeImage($path): string
    {
        list($width, $height, $type) = getimagesize($path);
        
        // Se a imagem for pequena, não mexe
        if ($width <= 1024) {
            return base64_encode(file_get_contents($path));
        }

        // Calcula nova altura mantendo proporção
        $newWidth = 1024;
        $newHeight = ($height / $width) * $newWidth;

        // Cria nova imagem na memória
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        
        // Carrega original baseado no tipo
        switch ($type) {
            case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG: $source = imagecreatefrompng($path); break;
            case IMAGETYPE_WEBP: $source = imagecreatefromwebp($path); break;
            default: return base64_encode(file_get_contents($path)); // Fallback se não suportado
        }

        // Redimensiona
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Salva em buffer (memória) como JPEG qualidade 80
        ob_start();
        imagejpeg($thumb, null, 80);
        $data = ob_get_clean();

        // Limpa memória
        imagedestroy($thumb);
        imagedestroy($source);

        return base64_encode($data);
    }
}