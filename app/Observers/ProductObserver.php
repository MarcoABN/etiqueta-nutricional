<?php

namespace App\Observers;

use App\Models\Product;
use App\Jobs\ProcessProductImage;

class ProductObserver
{
    /**
     * Evento disparado ANTES de salvar (bom para checar mudanças)
     * Mas para disparar o Job, usamos o UPDATED ou SAVED
     */
    public function saved(Product $product): void
    {
        // Verifica se o campo da imagem foi alterado ("sujo") e não está vazio
        if ($product->isDirty('image_nutritional') && !empty($product->image_nutritional)) {
            
            // Reseta o status para pendente
            // Usamos quiet() para não disparar este observer de novo num loop infinito
            $product->saveQuietly(['ai_status' => 'pending']);

            // Despacha para a fila
            ProcessProductImage::dispatch($product);
        }
    }
}