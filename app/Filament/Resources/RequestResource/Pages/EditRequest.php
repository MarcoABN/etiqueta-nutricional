<?php

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Request;
use App\Models\Pallet;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Livewire\Component;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class EditRequest extends EditRecord
{
    protected static string $resource = RequestResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            \App\Livewire\RequestItemsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // --- NOVA AÇÃO: CENTRAL DE EXPORTAÇÕES EXCEL (ActionGroup) ---
            Actions\ActionGroup::make([

                // Exportação 1: Invoice Completa
                Actions\Action::make('export_invoice')
                    ->label('Exportar Invoice')
                    ->icon('heroicon-o-document-text')
                    ->action(function (Request $record) {
                        return response()->streamDownload(function () use ($record) {

                            $writer = new Writer();
                            $writer->openToFile('php://output');

                            // ==========================================
                            // ABA 1: INVOICE
                            // ==========================================
                            $sheet1 = $writer->getCurrentSheet();
                            $sheet1->setName('Invoice');

                            $writer->addRow(Row::fromValues([
                                'ITENS',
                                'NCM',
                                'DESCRIPTION',
                                'UNID',
                                'QTDE',
                                'Vlr Unitario',
                                'TOTAL'
                            ]));

                            $items = $record->items()->with('product')->get();

                            $sequential = 1;
                            $sumQtde = 0;
                            $sumTotal = 0;
                            $sumNetWeight = 0;
                            $sheet2Data = [];

                            foreach ($items as $item) {
                                $ncm = $item->product ? $item->product->ncm : '';

                                $description = $item->product_name;
                                if ($item->product && !empty($item->product->product_name_en)) {
                                    $description = $item->product->product_name_en;
                                }

                                $unid = $item->packaging;
                                $qtde = (float) $item->quantity;
                                $vlrUnit = (float) ($item->unit_price ?? 0);
                                $total = $qtde * $vlrUnit;

                                $sumQtde += $qtde;
                                $sumTotal += $total;

                                $writer->addRow(Row::fromValues([
                                    $sequential,
                                    $ncm,
                                    $description,
                                    $unid,
                                    $qtde,
                                    $vlrUnit,
                                    $total
                                ]));

                                // Cálculos Aba 2
                                $pesoliq = 0;
                                $qtunitcx = 1;

                                if ($item->product) {
                                    $pesoliq = (float) str_replace(',', '.', $item->product->pesoliq ?? '0');
                                    $qtunitcx = (float) str_replace(',', '.', $item->product->qtunitcx ?? '1');
                                }

                                $netWeight = $qtunitcx * $pesoliq * $qtde;
                                $sumNetWeight += $netWeight;

                                $sheet2Data[] = [
                                    $sequential,
                                    $ncm,
                                    $description,
                                    $netWeight,
                                    '',
                                    $qtde
                                ];

                                $sequential++;
                            }

                            $writer->addRow(Row::fromValues(['', '', '', 'TOTAIS', $sumQtde, '', $sumTotal]));

                            // ==========================================
                            // ABA 2: PACKING LIST
                            // ==========================================
                            $sheet2 = $writer->addNewSheetAndMakeItCurrent();
                            $sheet2->setName('Packing List');

                            $writer->addRow(Row::fromValues([
                                'ITENS',
                                'NCM',
                                'DESCRIPTION',
                                'NET W.',
                                'GROSS W.',
                                'BOXES QTY'
                            ]));

                            foreach ($sheet2Data as $row) {
                                $writer->addRow(Row::fromValues($row));
                            }

                            $writer->addRow(Row::fromValues(['', '', 'TOTAIS', $sumNetWeight, '', $sumQtde]));

                            $writer->close();
                        }, "invoice_{$record->display_id}.xlsx");
                    }),

                // Exportação 2: Solicitação Completa
                Actions\Action::make('export_request')
                    ->label('Exportar Solicitação')
                    ->icon('heroicon-o-cube')
                    ->action(function (Request $record) {
                        return response()->streamDownload(function () use ($record) {
                            $writer = new Writer();
                            $writer->openToFile('php://output');

                            // Cabeçalho atualizado com as novas colunas
                            $writer->addRow(Row::fromValues([
                                'Cód WinThor',
                                'Produto',
                                'Qtd CX',
                                'Emb',
                                'Unidade Master', // Nova
                                'Peso Liq. Un',     // Nova
                                'Total Un',    // Nova
                                'Total em KG',              // Nova (Anterior)
                                'Valor UN',
                                'Valor Total',
                                'Observação'
                            ]));

                            // Carrega a relação de produtos para performance
                            $record->loadMissing('items.product');

                            foreach ($record->items as $item) {
                                $qtdCx = (float) $item->quantity;

                                // Valores padrão caso não haja produto atrelado (Produto Manual)
                                $qtunitcx = 1;
                                $pesoliq = 0;

                                if ($item->product) {
                                    // Prevenção de erros no PHP 8.2+ e tratamento de vírgulas
                                    $pesoliq = (float) str_replace(',', '.', (string) ($item->product->pesoliq ?? '0'));
                                    $qtunitcx = (float) str_replace(',', '.', (string) ($item->product->qtunitcx ?? '1'));
                                }

                                // Cálculos
                                $qtdTotal = $qtdCx * $qtunitcx;
                                $qtdKl = $qtdTotal * $pesoliq;

                                $writer->addRow(Row::fromValues([
                                    $item->winthor_code ?? 'Manual',
                                    $item->product_name,
                                    $qtdCx,
                                    $item->packaging,
                                    $qtunitcx, // Qtd. Unit. na Caixa
                                    $pesoliq,  // Peso Líquido un
                                    $qtdTotal, // Quantidade total
                                    $qtdKl,    // Qtd Kl
                                    (float) ($item->unit_price ?? 0),
                                    (float) ($qtdCx * ($item->unit_price ?? 0)),
                                    $item->observation ?? '',
                                ]));
                            }
                            $writer->close();
                        }, "Solicitacao_{$record->display_id}.xlsx");
                    }),

                // Exportação 3: Validades
                Actions\Action::make('export_expirations')
                    ->label('Exportar Validades')
                    ->icon('heroicon-o-calendar-days')
                    ->action(function (Request $record) {
                        return response()->streamDownload(function () use ($record) {
                            $writer = new Writer();
                            $writer->openToFile('php://output');

                            $writer->addRow(Row::fromValues(['Cód WinThor', 'Produto', 'Data de Validade', 'Qtd Lote']));

                            foreach ($record->items()->with('expirations')->get() as $item) {
                                if ($item->expirations && $item->expirations->count() > 0) {
                                    foreach ($item->expirations as $exp) {
                                        $writer->addRow(Row::fromValues([
                                            $item->winthor_code ?? 'Manual',
                                            $item->product_name,
                                            $exp->expiration_date->format('d/m/Y'),
                                            (float) $exp->quantity,
                                        ]));
                                    }
                                } else {
                                    $writer->addRow(Row::fromValues([
                                        $item->winthor_code ?? 'Manual',
                                        $item->product_name,
                                        'Sem validade informada',
                                        (float) $item->quantity,
                                    ]));
                                }
                            }
                            $writer->close();
                        }, "Validades_{$record->display_id}.xlsx");
                    }),

                // Exportação 4: Pallets (Removido Importador, Adicionado Total)
                Actions\Action::make('export_pallets')
                    ->label('Exportar Pallets')
                    ->icon('heroicon-o-archive-box')
                    ->action(function (Request $record) {
                        return response()->streamDownload(function () use ($record) {
                            $writer = new Writer();
                            $writer->openToFile('php://output');

                            $writer->addRow(Row::fromValues(['Nº Pallet', 'Peso Total / Gross W. (KG)', 'Altura / Height (m)']));

                            $pallets = Pallet::where('request_id', $record->id)
                                ->orderBy('pallet_number', 'asc')
                                ->get();

                            $totalWeight = 0;

                            foreach ($pallets as $pallet) {
                                $weight = (float) ($pallet->gross_weight ?? 0);
                                $totalWeight += $weight;

                                $writer->addRow(Row::fromValues([
                                    "{$pallet->pallet_number} / {$pallet->total_pallets}",
                                    $weight,
                                    (float) ($pallet->height ?? 0),
                                ]));
                            }

                            // Adiciona a linha de total
                            $writer->addRow(Row::fromValues([
                                'TOTAIS',
                                $totalWeight,
                                ''
                            ]));

                            $writer->close();
                        }, "Pallets_{$record->display_id}.xlsx");
                    }),
            ])
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->button() // Transforma o Dropdown num botão visual padrão
                ->color('success'),


            // --- AÇÃO INTACTA: IMPRIMIR ---
            Actions\Action::make('print')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->form([
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

                    Select::make('order_by')
                        ->label('Ordenação dos Itens')
                        ->options([
                            'product_name' => 'Descrição do Produto (Alfabética)',
                            'created_at' => 'Ordem de Inserção (Cronológica)',
                        ])
                        ->default('product_name')
                        ->required()
                        ->native(false),
                ])
                ->action(function (Request $record, array $data, Component $livewire) {
                    $url = route('request.print', [
                        'record' => $record,
                        'filter_type' => $data['filter_type'],
                        'order_by' => $data['order_by']
                    ]);

                    $livewire->js("window.open('$url', '_blank')");
                }),

            // --- AÇÃO INTACTA: DELETAR SOLICITAÇÃO ---
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
