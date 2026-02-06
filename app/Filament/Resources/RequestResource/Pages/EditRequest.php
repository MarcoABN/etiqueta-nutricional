<?php

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Request;
use Filament\Forms\Components\CheckboxList; // Importante

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
                // ADICIONADO: Formulário de Filtro
                ->form([
                    CheckboxList::make('shipping_types')
                        ->label('Selecione os tipos para exportar:')
                        ->options([
                            'Maritimo' => 'Maritimo',
                            'Aereo' => 'Aereo',
                            'Avaliar' => 'Avaliar',
                        ])
                        ->default(['Maritimo', 'Aereo', 'Avaliar'])
                        ->required()
                        ->columns(3),
                ])
                ->action(function (Request $record, array $data) {
                    return response()->streamDownload(function () use ($record, $data) {
                        echo "\xEF\xBB\xBF";
                        $handle = fopen('php://output', 'w');
                        
                        // FILTRO: Usa os tipos selecionados no modal
                        $selectedTypes = $data['shipping_types'];
                        $items = $record->items()
                            ->whereIn('shipping_type', $selectedTypes)
                            ->get();

                        $registered = $items->filter(fn($i) => !empty($i->product_id));
                        $manual = $items->filter(fn($i) => empty($i->product_id));

                        if ($registered->isNotEmpty()) {
                            fputcsv($handle, ['--- PRODUTOS CADASTRADOS ---'], ';');
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

                        if ($registered->isNotEmpty() && $manual->isNotEmpty()) {
                            fputcsv($handle, [], ';'); 
                            fputcsv($handle, [], ';'); 
                        }

                        if ($manual->isNotEmpty()) {
                            fputcsv($handle, ['--- ITENS MANUAIS ---'], ';');
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
                // ADICIONADO: Formulário de Filtro
                ->form([
                    CheckboxList::make('shipping_types')
                        ->label('Selecione os tipos para imprimir:')
                        ->options([
                            'Maritimo' => 'Maritimo',
                            'Aereo' => 'Aereo',
                            'Avaliar' => 'Avaliar',
                        ])
                        ->default(['Maritimo', 'Aereo', 'Avaliar'])
                        ->required()
                        ->columns(3),
                ])
                ->action(function (Request $record, array $data) {
                    // Monta a URL passando os tipos como parâmetros query string
                    $url = route('requests.print', [
                        'record' => $record,
                        'types' => $data['shipping_types']
                    ]);
                    
                    // Redireciona para a rota de impressão
                    return redirect()->away($url);
                }),

            // BOTÃO EXCLUIR
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}