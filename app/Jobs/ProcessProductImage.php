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

    // Timeout do Job deve ser maior que o timeout do HTTP request
    public $timeout = 200;
    
    // Tentar apenas 1 vez para não travar a fila com erros repetidos. 
    // Se falhar, vai para tabela de failed_jobs e status vira 'error'
    public $tries = 1; 

    public function __construct(
        public Product $product
    ) {}

    public function handle(OllamaService $ollama): void
    {
        // 1. Atualiza status para processando
        $this->product->update(['ai_status' => 'processing']);

        try {
            if (!$this->product->image_nutritional) {
                throw new \Exception("Imagem não encontrada no registro.");
            }

            $path = Storage::disk('public')->path($this->product->image_nutritional);
            
            if (!file_exists($path)) {
                throw new \Exception("Arquivo físico não encontrado: $path");
            }

            // 2. Converte e Envia
            $b64 = base64_encode(file_get_contents($path));
            $data = $ollama->extractNutritionalData($b64);

            if (!$data) {
                throw new \Exception("Ollama retornou vazio ou falhou.");
            }

            // 3. Salva e Conclui
            // O array $data já tem as chaves iguais às colunas do DB graças ao prompt
            $this->product->update(array_merge($data, [
                'ai_status' => 'completed',
                'last_ai_processed_at' => now(),
                'ai_error_message' => null
            ]));

            Log::info("Produto {$this->product->id} processado com sucesso via Ollama.");

        } catch (\Exception $e) {
            $this->product->update([
                'ai_status' => 'error',
                'ai_error_message' => $e->getMessage()
            ]);
            Log::error("Falha no produto {$this->product->id}: " . $e->getMessage());
            
            // Re-lança para o Laravel registrar no failed_jobs se quiser monitorar lá também
            throw $e;
        }
    }
}