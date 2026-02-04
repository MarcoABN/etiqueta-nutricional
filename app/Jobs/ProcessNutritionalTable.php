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

class ProcessNutritionalTable implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Aumentamos o timeout do Job para o Laravel não matar ele antes da IA responder
    public $timeout = 180; 

    public function __construct(
        public Product $product
    ) {}

    public function handle(OllamaService $ollama): void
    {
        Log::info("Iniciando processamento IA para o produto: {$this->product->id}");

        if (!$this->product->image_nutritional) {
            return;
        }

        // 1. Carregar a imagem do disco
        $path = Storage::disk('public')->path($this->product->image_nutritional);
        
        if (!file_exists($path)) {
            Log::error("Imagem não encontrada: $path");
            return;
        }

        // 2. Converter para Base64 (requisito da API do Ollama)
        $b64 = base64_encode(file_get_contents($path));

        // 3. Chamar o serviço
        $data = $ollama->extractNutritionalData($b64);

        if ($data) {
            // 4. Salvar no banco
            $this->product->update([
                'nutritional_data' => $data
            ]);
            
            Log::info("Dados nutricionais salvos para produto {$this->product->id}");
        } else {
            Log::warning("Falha ao extrair dados para produto {$this->product->id}");
        }
    }
}