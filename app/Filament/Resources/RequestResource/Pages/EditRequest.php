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
use Illuminate\Support\Str;

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

            // --- AÇÃO: CONSOLIDAR SOLICITAÇÃO ---
            Actions\Action::make('consolidate_request')
                ->label('Consolidar Solicitação')
                ->icon('heroicon-o-lock-closed')
                ->color('success') // Segue o mesmo padrão visual verde do fechamento
                ->requiresConfirmation()
                ->modalHeading('Consolidar Solicitação?')
                ->modalDescription('Após consolidar, você não poderá alterar os dados gerais da solicitação nem gerenciar seus itens. Deseja continuar?')
                ->hidden(fn() => $this->record->is_locked)
                ->action(function () {
                    $this->record->update(['is_locked' => true]);
                    \Filament\Notifications\Notification::make()
                        ->title('Solicitação consolidada com sucesso!')
                        ->success()
                        ->send();
                }),

            // --- AÇÃO: REABRIR SOLICITAÇÃO ---
            Actions\Action::make('reopen_request')
                ->label('Reabrir Solicitação')
                ->icon('heroicon-o-lock-open')
                // Se o fechamento estiver consolidado, fica cinza. Se não, fica laranja (warning)
                ->color(fn() => ($this->record->settlement?->is_locked ?? false) ? 'gray' : 'warning')
                ->tooltip(
                    fn() => ($this->record->settlement?->is_locked ?? false)
                        ? '⚠️ Bloqueado: Reabra o Fechamento primeiro.'
                        : 'Clique para reabrir a solicitação'
                )
                ->requiresConfirmation()
                ->modalHeading('Reabrir Solicitação?')
                ->modalDescription(
                    fn() => ($this->record->settlement?->is_locked ?? false)
                        ? new \Illuminate\Support\HtmlString('<span class="text-danger-600 font-medium">Ação Bloqueada:</span> O fechamento financeiro associado a esta solicitação está consolidado. Você deve ir até o painel de <strong>Fechamentos</strong> e reabri-lo antes de reabrir a solicitação.')
                        : 'Isso permitirá que os itens e dados da solicitação sejam editados novamente. Deseja continuar?'
                )
                ->modalSubmitAction(fn($action) => ($this->record->settlement?->is_locked ?? false) ? $action->hidden() : $action)
                ->visible(fn() => $this->record->is_locked)
                ->action(function () {
                    if ($this->record->settlement?->is_locked ?? false) {
                        return;
                    }

                    $this->record->update(['is_locked' => false]);
                    \Filament\Notifications\Notification::make()
                        ->title('Solicitação reaberta para edição!')
                        ->warning()
                        ->send();
                }),

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
                                // 1. Busca NCM e Descrição primeiro do Snapshot, depois do Produto (Fallback)
                                $ncm = $item->ncm ?: ($item->product?->ncm ?: '');
                                $description = $item->product_name_en ?: ($item->product?->product_name_en ?: $item->product_name);

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

                                // 2. Cálculos Aba 2 (SEM O IF)
                                // Lê do snapshot. Se for nulo, tenta ler do produto formatando a string. Se não tiver produto, assume 0 (ou 1 para caixa).
                                $pesoliq = (float) ($item->pesoliq ?? ($item->product ? str_replace(',', '.', (string) ($item->product->pesoliq ?? '0')) : 0));
                                $qtunitcx = (float) ($item->qtunitcx ?? ($item->product ? str_replace(',', '.', (string) ($item->product->qtunitcx ?? '1')) : 1));

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
                        }, "invoice_" . Str::slug($record->observation ?? 'avulso') . ".xlsx");
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

                                // SEM O IF: Pega o Snapshot, faz Fallback para o Produto e, se tudo falhar, assume 0/1 (Produto Manual Exclusivo)
                                $pesoliq = (float) ($item->pesoliq ?? ($item->product ? str_replace(',', '.', (string) ($item->product->pesoliq ?? '0')) : 0));
                                $qtunitcx = (float) ($item->qtunitcx ?? ($item->product ? str_replace(',', '.', (string) ($item->product->qtunitcx ?? '1')) : 1));

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
                        }, "Solicitacao_" . Str::slug($record->observation ?? 'avulso') . ".xlsx");
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
                        }, "Validades_" . Str::slug($record->observation ?? 'avulso') . ".xlsx");
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
                        }, "Pallets_" . Str::slug($record->observation ?? 'avulso') . ".xlsx");
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
            Actions\DeleteAction::make()
                ->hidden(fn() => $this->record->is_locked || ($this->record->settlement?->is_locked ?? false)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()
            ->hidden(fn() => $this->record->is_locked || ($this->record->settlement?->is_locked ?? false));
    }
}
