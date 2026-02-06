<?php

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Request;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Livewire\Component; // [IMPORTANTE] Necessário para abrir nova aba

class EditRequest extends EditRecord
{
    protected static string $resource = RequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // --- AÇÃO DE EXPORTAR (Mantém o download direto) ---
            Actions\Action::make('export_csv')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
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

                    Radio::make('filter_type')
                        ->label('Filtrar por origem:')
                        ->options([
                            'all' => 'Todos',
                            'registered' => 'Somente Cadastrados',
                            'manual' => 'Somente Manuais',
                        ])
                        ->default('all')
                        ->inline()
                        ->required(),
                ])
                ->action(function (Request $record, array $data) {
                    return response()->streamDownload(function () use ($record, $data) {
                        echo "\xEF\xBB\xBF"; // BOM para Excel abrir corretamente acentos
                        $handle = fopen('php://output', 'w');
                        
                        // 1. Filtra por Tipo de Envio
                        $query = $record->items()->whereIn('shipping_type', $data['shipping_types']);

                        // 2. Filtra por Origem (Cadastrado vs Manual)
                        if ($data['filter_type'] === 'registered') {
                            $query->whereNotNull('product_id');
                        } elseif ($data['filter_type'] === 'manual') {
                            $query->whereNull('product_id');
                        }

                        $items = $query->get();

                        // Separa para gerar o relatório organizado
                        $registered = $items->filter(fn($i) => !empty($i->product_id));
                        $manual = $items->filter(fn($i) => empty($i->product_id));

                        // BLOCO 1: Cadastrados
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

                        // Espaçamento
                        if ($registered->isNotEmpty() && $manual->isNotEmpty()) {
                            fputcsv($handle, [], ';'); 
                            fputcsv($handle, [], ';'); 
                        }

                        // BLOCO 2: Manuais
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

            // --- AÇÃO DE IMPRIMIR (Abre em Nova Aba) ---
            Actions\Action::make('print')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
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

                    Radio::make('filter_type')
                        ->label('Filtrar por origem:')
                        ->options([
                            'all' => 'Todos',
                            'registered' => 'Somente Cadastrados',
                            'manual' => 'Somente Manuais',
                        ])
                        ->default('all')
                        ->inline()
                        ->required(),
                ])
                // [ALTERAÇÃO AQUI] Injeção do componente $livewire para rodar JS
                ->action(function (Request $record, array $data, Component $livewire) {
                    $url = route('request.print', [
                        'record' => $record,
                        'types' => $data['shipping_types'],
                        'filter_type' => $data['filter_type']
                    ]);
                    
                    // Comando JS para abrir nova aba
                    $livewire->js("window.open('$url', '_blank')");
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}