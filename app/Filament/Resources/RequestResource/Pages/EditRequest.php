<?php

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Request;

class EditRequest extends EditRecord
{
    protected static string $resource = RequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // BOTÃO EXPORTAR (Excel / CSV)
            Actions\Action::make('export_csv')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (Request $record) {
                    return response()->streamDownload(function () use ($record) {
                        echo "\xEF\xBB\xBF"; // Adiciona BOM para que o Excel abra os acentos corretamente
                        $handle = fopen('php://output', 'w');
                        
                        // Busca e filtra os itens
                        $items = $record->items;
                        $registered = $items->filter(fn($i) => !empty($i->product_id));
                        $manual = $items->filter(fn($i) => empty($i->product_id));

                        // --- BLOCO 1: PRODUTOS CADASTRADOS ---
                        if ($registered->isNotEmpty()) {
                            // Título da Seção
                            fputcsv($handle, ['--- PRODUTOS CADASTRADOS (WINTHOR) ---'], ';');
                            // Cabeçalho das Colunas
                            fputcsv($handle, ['ID Pedido', 'Cód WinThor', 'Produto', 'Qtd', 'Emb', 'Envio', 'Obs'], ';');

                            foreach ($registered as $item) {
                                fputcsv($handle, [
                                    $record->display_id,
                                    $item->winthor_code,
                                    $item->product_name,
                                    number_format($item->quantity, 2, ',', '.'),
                                    $item->packaging,
                                    $item->shipping_type,
                                    $item->observation
                                ], ';');
                            }
                        }

                        // --- SEPARADOR VISUAL (Linhas em branco) ---
                        if ($registered->isNotEmpty() && $manual->isNotEmpty()) {
                            fputcsv($handle, [], ';'); 
                            fputcsv($handle, [], ';'); 
                        }

                        // --- BLOCO 2: ITENS MANUAIS ---
                        if ($manual->isNotEmpty()) {
                            // Título da Seção
                            fputcsv($handle, ['--- ITENS MANUAIS / SEM CADASTRO ---'], ';');
                            // Cabeçalho das Colunas (Adaptado)
                            fputcsv($handle, ['ID Pedido', 'Tipo', 'Descrição do Item', 'Qtd', 'Emb', 'Envio', 'Obs'], ';');

                            foreach ($manual as $item) {
                                fputcsv($handle, [
                                    $record->display_id,
                                    'MANUAL',
                                    $item->product_name,
                                    number_format($item->quantity, 2, ',', '.'),
                                    $item->packaging,
                                    $item->shipping_type,
                                    $item->observation
                                ], ';');
                            }
                        }

                        fclose($handle);
                    }, "pedido_{$record->display_id}.csv");
                }),

            // BOTÃO IMPRIMIR
            Actions\Action::make('print')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->url(fn (Request $record) => route('requests.print', $record))
                ->openUrlInNewTab(), // Abre a visualização de impressão em nova aba

            // BOTÃO EXCLUIR
            Actions\DeleteAction::make(),
        ];
    }

    // Redireciona para a listagem após salvar alterações no cabeçalho
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}