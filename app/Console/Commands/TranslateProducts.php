<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\GeminiFdaTranslator;
use Illuminate\Console\Command;

class TranslateProducts extends Command
{
    // Nome do comando para rodar no terminal
    protected $signature = 'products:translate-all {--limit=1000 : Limite de itens para processar hoje}';
    
    protected $description = 'Traduz produtos sem descrição em inglês usando Gemini (respeitando limites grátis)';

    public function handle()
    {
        $limit = $this->option('limit');
        $translator = new GeminiFdaTranslator();

        // 1. Busca apenas produtos que AINDA NÃO têm tradução
        // Isso permite rodar o comando vários dias seguidos sem repetir trabalho
        $query = Product::whereNull('product_name_en')
                        ->orWhere('product_name_en', '')
                        ->whereNotNull('product_name'); // Tem que ter nome em PT

        $totalFound = $query->count();

        if ($totalFound === 0) {
            $this->info("Nenhum produto pendente de tradução!");
            return;
        }

        $this->info("Encontrados {$totalFound} produtos pendentes.");
        $this->info("Processando lote de {$limit} itens...");

        $bar = $this->output->createProgressBar($limit);
        $bar->start();

        // Pega o lote
        $products = $query->take($limit)->get();
        $success = 0;
        $errors = 0;

        foreach ($products as $product) {
            try {
                $translated = $translator->translate($product->product_name);

                if ($translated) {
                    $product->update(['product_name_en' => $translated]);
                    $success++;
                } else {
                    $errors++;
                    $this->warn("\nFalha ao traduzir: {$product->product_name}");
                }

            } catch (\Exception $e) {
                $this->error("\nErro crítico: " . $e->getMessage());
                $errors++;
            }

            $bar->advance();

            // PAUSA OBRIGATÓRIA PARA PLANO FREE (4 segundos)
            // 15 requisições por minuto = 1 a cada 4s
            sleep(4);
        }

        $bar->finish();
        $this->newLine(2);
        
        $this->info("Lote finalizado!");
        $this->info("Traduzidos com sucesso: {$success}");
        $this->info("Falhas: {$errors}");
        $this->comment("Dica: Se houver muitos pendentes, rode este comando novamente amanhã.");
    }
}