<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\GeminiFdaTranslator;
use Illuminate\Console\Command;

class TranslateProducts extends Command
{
    protected $signature = 'products:translate-all {--limit=1000 : Limite de itens}';
    protected $description = 'Traduz produtos em massa usando estratégia Híbrida (Google -> Perplexity)';

    public function handle()
    {
        $limit = $this->option('limit');
        $translator = new GeminiFdaTranslator();

        // Pega apenas quem NÃO tem tradução
        $query = Product::whereNull('product_name_en')
                        ->orWhere('product_name_en', '')
                        ->whereNotNull('product_name');

        $totalFound = $query->count();

        if ($totalFound === 0) {
            $this->info("Tudo traduzido! Nenhum pendente.");
            return;
        }

        $this->info("Pendentes: {$totalFound}. Processando lote de {$limit}...");
        $bar = $this->output->createProgressBar($limit);
        $bar->start();

        $products = $query->take($limit)->get();
        $success = 0;

        foreach ($products as $product) {
            try {
                // O Serviço já gerencia se vai usar Google ou Perplexity
                // O Serviço já tem o 'sleep' interno para o Google não bloquear
                $translated = $translator->translate($product->product_name);

                if ($translated) {
                    $product->update(['product_name_en' => $translated]);
                    $success++;
                }

            } catch (\Exception $e) {
                // Falhas silenciosas para não parar o lote
            }

            $bar->advance();
            // Pequeno respiro geral para o servidor não sobrecarregar
            usleep(200000); 
        }

        $bar->finish();
        $this->newLine();
        $this->info("Processo finalizado! Traduzidos: {$success}");
    }
}