<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettlementResource\Pages;
use App\Models\Settlement;
use App\Models\Request;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;

class SettlementResource extends Resource
{
    protected static ?string $model = Settlement::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Fechamentos';
    protected static ?string $modelLabel = 'Fechamento';
    protected static ?string $pluralModelLabel = 'Fechamentos';
    protected static ?string $navigationGroup = 'Gestão';
    protected static ?int $navigationSort = 2;

    public static function updateTotals(Get $get, Set $set): void
    {
        $requestId = $get('request_id') ?? $get('../../request_id');
        if (!$requestId) return;

        $request = Request::with('items')->find($requestId);
        if (!$request) return;

        $factorRaw = $get('calculation_factor') ?? $get('../../calculation_factor');
        $factor = (float) ($factorRaw ?: 70);
        $factorDec = $factor > 0 ? $factor / 100 : 0.70;

        $initialTotal = 0;
        $totalValue = 0;

        foreach ($request->items as $item) {
            $initial = ($item->quantity ?? 0) * ($item->unit_price ?? 0);
            $initialTotal += $initial;

            $partial = $factorDec > 0 ? $initial / $factorDec : 0;
            $totalValue += $partial;
        }

        $expenses = $get('expenses') ?? $get('../../expenses') ?? [];
        $totalExpenses = collect($expenses)->sum('amount');

        $expensePercentage = $totalValue > 0 ? ($totalExpenses / $totalValue) * 100 : 0;
        $overallTotal = $totalValue + $totalExpenses;

        $isRepeater = $get('request_id') === null;
        $prefix = $isRepeater ? '../../' : '';

        $set($prefix . 'initial_total', round($initialTotal, 2));
        $set($prefix . 'total_value', round($totalValue, 2));
        $set($prefix . 'total_expenses', round($totalExpenses, 2));
        $set($prefix . 'expense_percentage', round($expensePercentage, 3));

        $set($prefix . 'overall_total', round($overallTotal, 2));

        $showInUsd = $get($prefix . 'show_in_usd');
        $quote = floatval($get($prefix . 'usd_quote'));
        $isUsd = $showInUsd && $quote > 0;

        $dispInit = $isUsd ? $initialTotal / $quote : $initialTotal;
        $dispVal = $isUsd ? $totalValue / $quote : $totalValue;
        $dispExp = $isUsd ? $totalExpenses / $quote : $totalExpenses;
        $dispOverall = $isUsd ? $overallTotal / $quote : $overallTotal;

        $set($prefix . 'display_initial_total', number_format($dispInit, 2, '.', ''));
        $set($prefix . 'display_total_value', number_format($dispVal, 2, '.', ''));
        $set($prefix . 'display_total_expenses', number_format($dispExp, 2, '.', ''));
        $set($prefix . 'display_overall_total', number_format($dispOverall, 2, '.', ''));
        $set($prefix . 'display_expense_percentage', number_format($expensePercentage, 3, '.', ''));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('show_in_usd')
                    ->default(false)
                    ->dehydrated(false),
                Forms\Components\Hidden::make('initial_total'),
                Forms\Components\Hidden::make('total_value'),
                Forms\Components\Hidden::make('total_expenses'),
                Forms\Components\Hidden::make('expense_percentage'),

                Forms\Components\Hidden::make('overall_total'),

                Forms\Components\Section::make('Dados e Totais do Fechamento')
                    ->schema([
                        Forms\Components\Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                            ->schema([
                                Forms\Components\Select::make('request_id')
                                    ->label('Solicitação')
                                    ->options(Request::where('status', 'fechado')->pluck('display_id', 'id'))
                                    ->required()
                                    ->unique(ignoreRecord: true, modifyRuleUsing: fn(Unique $rule) => $rule->whereNull('deleted_at'))
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Não é permitido criar mais de um fechamento por solicitação.'),

                                Forms\Components\TextInput::make('usd_quote')
                                    ->label('Cotação USD')
                                    ->numeric()
                                    ->step(0.0001) // Adicionado: Pula de 1 em 1 centavo
                                    ->minValue(0) // Adicionado: Impede cotação negativa
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                                    ->prefixAction(
                                        Forms\Components\Actions\Action::make('toggle_currency')
                                            ->label('US$')
                                            ->icon(fn(Get $get) => $get('show_in_usd') ? 'heroicon-m-currency-dollar' : 'heroicon-m-arrows-right-left')
                                            ->color(fn(Get $get) => $get('show_in_usd') ? 'success' : 'gray')
                                            ->tooltip('Alternar exibição de todos os valores da tela entre Real e Dólar')
                                            ->disabled(fn(Get $get) => !(floatval($get('usd_quote')) > 0))
                                            ->action(function (Set $set, Get $get) {
                                                $set('show_in_usd', !$get('show_in_usd'));
                                                self::updateTotals($get, $set);
                                            })
                                    )
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Valor da cotação. Clique para alternar as moedas.'),

                                Forms\Components\TextInput::make('calculation_factor')
                                    ->label('Fator (%)')
                                    ->numeric()
                                    ->default(70)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Porcentagem usada para retornar ao valor original do produto.'),

                                Forms\Components\TextInput::make('display_total_expenses')
                                    ->label('Despesas (Soma)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->total_expenses, 2, '.', '') : '0.00'))
                                    ->prefix(fn(Get $get) => $get('show_in_usd') && floatval($get('usd_quote')) > 0 ? 'US$' : 'R$')
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Soma de todas as despesas listadas.'),

                                Forms\Components\TextInput::make('display_expense_percentage')
                                    ->label('% Despesa')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->expense_percentage, 3, '.', '') : '0.000'))
                                    ->suffix('%')
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Representatividade das despesas sobre o Total Parcial.'),
                            ]),

                        Forms\Components\Grid::make(['default' => 1, 'sm' => 3, 'lg' => 3])
                            ->schema([
                                Forms\Components\TextInput::make('display_initial_total')
                                    ->label('Total Inicial')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->items()->sum('initial_value'), 2, '.', '') : '0.00'))
                                    ->prefix(fn(Get $get) => $get('show_in_usd') && floatval($get('usd_quote')) > 0 ? 'US$' : 'R$')
                                    ->extraInputAttributes(['class' => 'text-lg font-bold text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/30', 'style' => '-webkit-text-fill-color: currentcolor; opacity: 1;'])
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Soma dos Valores Iniciais de todos os produtos. Deve Refletir o valor da NF Emitida para exportação.'),

                                Forms\Components\TextInput::make('display_total_value')
                                    ->label('Total Parcial (Produtos)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->total_value, 2, '.', '') : '0.00'))
                                    ->prefix(fn(Get $get) => $get('show_in_usd') && floatval($get('usd_quote')) > 0 ? 'US$' : 'R$')
                                    ->extraInputAttributes(['class' => 'text-lg font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30', 'style' => '-webkit-text-fill-color: currentcolor; opacity: 1;'])
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Valor total do pedido com correção aplicada SEM a soma das despesas.'),

                                Forms\Components\TextInput::make('display_overall_total')
                                    ->label('Total Geral (Produtos + Despesas)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->total_value + $record->total_expenses, 2, '.', '') : '0.00'))
                                    ->prefix(fn(Get $get) => $get('show_in_usd') && floatval($get('usd_quote')) > 0 ? 'US$' : 'R$')
                                    ->extraInputAttributes(['class' => 'text-2xl bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-300 font-extrabold', 'style' => '-webkit-text-fill-color: currentcolor; opacity: 1; padding-top: 1rem; padding-bottom: 1rem; height: auto;'])
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Valor total final (Valor da Nota + Correção + Despesas).'),
                            ])->extraAttributes(['class' => 'mt-2 border-t border-gray-200 dark:border-white/10 pt-4']),
                    ]),

                Forms\Components\Section::make('Despesas do Fechamento')
                    ->schema([
                        TableRepeater::make('expenses')
                            ->relationship('expenses')
                            ->orderColumn('expense_number')
                            ->hiddenLabel()
                            ->addActionLabel('Adicionar Despesa')
                            ->reorderable(true)
                            ->live() // 1. Devolvemos a reatividade ao Repeater para ele atualizar o prefixo (R$/US$)
                            ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                            ->colStyles([
                                'description' => 'width: 75%;',
                                'amount' => 'width: 25%;',
                            ])
                            ->schema([
                                Forms\Components\TextInput::make('description')
                                    ->label('Descrição da Despesa')
                                    ->placeholder('Ex: Frete Marítimo, Pallets...')
                                    ->required(),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Valor')
                                    ->numeric()
                                    ->prefix(fn(Get $get) => $get('../../show_in_usd') && floatval($get('../../usd_quote')) > 0 ? 'US$' : 'R$')
                                    ->required()
                                    ->live(onBlur: true) // 2. O SEGREDO: Só recalcula quando o usuário sai do campo, impedindo que os dígitos sumam!
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotals($get, $set)),
                            ])
                            ->deleteAction(
                                fn(\Filament\Forms\Components\Actions\Action $action) => $action
                                    ->after(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                            )
                            ->columnSpan('full'),
                    ]),

                Forms\Components\Section::make('Pré-visualização dos Itens Rateados')
                    ->heading(new HtmlString('
                        <div class="flex items-center gap-2">
                            Pré-visualização dos Itens Rateados
                            <span x-data="{}" x-tooltip="\'O rateio das despesas é calculado de acordo com a % que o valor partial de cada produto representa do total parcial.\'">
                                <svg class="h-5 w-5 text-gray-400 hover:text-gray-500 cursor-help outline-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM8.94 6.94a.75.75 0 11-1.061-1.061 3 3 0 112.871 5.026v.345a.75.75 0 01-1.5 0v-.518a1 1 0 001-1 1.5 1.5 0 10-1.31-1.792zm-.44 9.06a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </div>
                    '))
                    ->schema([
                        Forms\Components\Placeholder::make('items_preview')
                            ->hiddenLabel()
                            ->content(fn(Get $get) => view('filament.components.settlement-items-preview', [
                                'requestId' => $get('request_id') ?? $get('../../request_id'),
                                'factor' => $get('calculation_factor') ?? $get('../../calculation_factor'),
                                'totalExpenses' => $get('total_expenses') ?? $get('../../total_expenses'),
                                'totalValue' => $get('total_value') ?? $get('../../total_value'),
                                'usdQuote' => $get('usd_quote') ?? $get('../../usd_quote'),
                                'showInUsd' => $get('show_in_usd') ?? $get('../../show_in_usd'),
                            ])),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request.display_id')->label('Solicitação')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('usd_quote')
                    ->label('Cot. USD')
                    ->formatStateUsing(fn($state) => 'US$ ' . number_format((float) $state, 4, ',', '.'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_value')->label('Total Parcial')->money('BRL'),

                Tables\Columns\TextColumn::make('overall_total')
                    ->label('Total Geral')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_expenses')->label('Despesas')->money('BRL'),
                Tables\Columns\TextColumn::make('expense_percentage')->label('% Despesa')->suffix('%'),
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('export_details')
                    ->label('Exportar')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (Settlement $record) {
                        $fileName = 'Fechamento_' . ($record->request->display_id ?? 'Avulso') . '.xlsx';

                        return response()->streamDownload(function () use ($record) {
                            $options = new Options();
                            $writer = new Writer($options);
                            $writer->openToFile('php://output');

                            // ==========================================
                            // PREPARAÇÃO DE DADOS E HELPER DE CONVERSÃO
                            // ==========================================
                            $initialTotal = $record->items()->sum('initial_value');
                            $overallTotal = $record->overall_total;
                            $expenses = $record->expenses()->orderBy('expense_number')->get();
                            $items = $record->items()->with('requestItem.product')->get();
                            $totalVal = (float) $record->total_value;

                            // Função helper para converter e arredondar para Dólar de forma segura
                            $usdQuote = (float) $record->usd_quote;
                            $toUsd = fn($value) => $usdQuote > 0 ? round((float) $value / $usdQuote, 2) : 0;

                            // ==========================================
                            // ABA 1: RESUMO E DESPESAS (BRL)
                            // ==========================================
                            $sheet1 = $writer->getCurrentSheet();
                            $sheet1->setName('Resumo e Despesas (R$)');

                            $writer->addRow(Row::fromValues(['Fechamento']));
                            $writer->addRow(Row::fromValues([
                                'Solicitação:',
                                $record->request->display_id ?? '-',
                                'Modalidade Envio:',
                                $record->request->shipping_type ?? 'Não Informado',
                                'Cotação USD:',
                                round($usdQuote, 4),
                                'Fator de Cálculo:',
                                round((float) $record->calculation_factor, 2) . '%',
                            ]));

                            $writer->addRow(Row::fromValues([
                                'Total Inicial:',
                                round((float) $initialTotal, 2),
                                'Total Parcial:',
                                round((float) $record->total_value, 2),
                                'Total Despesas:',
                                round((float) $record->total_expenses, 2),
                                '% Despesa:',
                                round((float) $record->expense_percentage, 2) . '%',
                                'Total Geral:',
                                round((float) $overallTotal, 2)
                            ]));

                            $writer->addRow(Row::fromValues([]));
                            $writer->addRow(Row::fromValues(['Despesas']));
                            $writer->addRow(Row::fromValues(['Descrição da Despesa', 'Valor (R$)']));

                            if ($expenses->isEmpty()) {
                                $writer->addRow(Row::fromValues(['Nenhuma despesa lançada.', 0]));
                            } else {
                                foreach ($expenses as $exp) {
                                    $writer->addRow(Row::fromValues([$exp->description, round((float) $exp->amount, 2)]));
                                }
                            }

                            // ==========================================
                            // ABA 2: DETALHAMENTO DE PRODUTOS (BRL)
                            // ==========================================
                            $sheet2 = $writer->addNewSheetAndMakeItCurrent();
                            $sheet2->setName('Detalhamento (R$)');

                            $headersBrl = [
                                'Cód. Winthor',
                                'Nome PT',
                                'Nome EN',
                                'Cód. Barras',
                                'Qtd Caixa',
                                'QTD',
                                'V. UN (R$)',
                                'Valor Inicial (R$)',
                                'Valor Parcial (R$)',
                                'Rateio Despesas (R$)',
                                '% Participação rateio',
                                'Valor Final (R$)'
                            ];
                            $writer->addRow(Row::fromValues($headersBrl));

                            foreach ($items as $item) {
                                $reqItem = $item->requestItem;
                                $product = $reqItem?->product;
                                $percentage = $totalVal > 0 ? ((float) $item->partial_value / $totalVal) : 0;
                                $totalApportionment = $item->final_value - $item->partial_value;

                                $writer->addRow(Row::fromValues([
                                    $reqItem?->winthor_code ?? $product?->codprod ?? '-',
                                    $reqItem?->product_name ?? '-',
                                    $product?->product_name_en ?? '-',
                                    $product?->barcode ?? $product?->ean ?? '-',
                                    $product?->qtunitcx ?? '-',
                                    round((float) ($reqItem?->quantity ?? 0), 2),
                                    round((float) ($reqItem?->unit_price ?? 0), 2),
                                    round((float) $item->initial_value, 2),
                                    round((float) $item->partial_value, 2),
                                    round((float) $totalApportionment, 2),
                                    round((float) ($percentage * 100), 2),
                                    round((float) $item->final_value, 2),
                                ]));
                            }

                            // ==========================================
                            // ABA 3: RESUMO E DESPESAS (USD)
                            // ==========================================
                            $sheet3 = $writer->addNewSheetAndMakeItCurrent();
                            $sheet3->setName('Resumo e Despesas (US$)');

                            $writer->addRow(Row::fromValues(['Fechamento (Valores em Dólar)']));
                            $writer->addRow(Row::fromValues([
                                'Solicitação:',
                                $record->request->display_id ?? '-',
                                'Modalidade Envio:',
                                $record->request->shipping_type ?? 'Não Informado',
                                'Cotação USD:',
                                round($usdQuote, 4), // Cotação não é convertida
                                'Fator de Cálculo:',
                                round((float) $record->calculation_factor, 2) . '%',
                            ]));

                            $writer->addRow(Row::fromValues([
                                'Total Inicial:',
                                $toUsd($initialTotal),
                                'Total Parcial:',
                                $toUsd($record->total_value),
                                'Total Despesas:',
                                $toUsd($record->total_expenses),
                                '% Despesa:',
                                round((float) $record->expense_percentage, 2) . '%', // Porcentagem é mantida
                                'Total Geral:',
                                $toUsd($overallTotal)
                            ]));

                            $writer->addRow(Row::fromValues([]));
                            $writer->addRow(Row::fromValues(['Despesas']));
                            $writer->addRow(Row::fromValues(['Descrição da Despesa', 'Valor (US$)']));

                            if ($expenses->isEmpty()) {
                                $writer->addRow(Row::fromValues(['Nenhuma despesa lançada.', 0]));
                            } else {
                                foreach ($expenses as $exp) {
                                    $writer->addRow(Row::fromValues([$exp->description, $toUsd($exp->amount)]));
                                }
                            }

                            // ==========================================
                            // ABA 4: DETALHAMENTO DE PRODUTOS (USD)
                            // ==========================================
                            $sheet4 = $writer->addNewSheetAndMakeItCurrent();
                            $sheet4->setName('Detalhamento (US$)');

                            $headersUsd = [
                                'Cód. Winthor',
                                'Nome PT',
                                'Nome EN',
                                'Cód. Barras',
                                'Qtd Caixa',
                                'QTD',
                                'V. UN (US$)',
                                'Valor Inicial (US$)',
                                'Valor Parcial (US$)',
                                'Rateio Despesas (US$)',
                                '% Participação rateio',
                                'Valor Final (US$)'
                            ];
                            $writer->addRow(Row::fromValues($headersUsd));

                            foreach ($items as $item) {
                                $reqItem = $item->requestItem;
                                $product = $reqItem?->product;
                                $percentage = $totalVal > 0 ? ((float) $item->partial_value / $totalVal) : 0;
                                $totalApportionment = $item->final_value - $item->partial_value;

                                $writer->addRow(Row::fromValues([
                                    $reqItem?->winthor_code ?? $product?->codprod ?? '-',
                                    $reqItem?->product_name ?? '-',
                                    $product?->product_name_en ?? '-',
                                    $product?->barcode ?? $product?->ean ?? '-',
                                    $product?->qtunitcx ?? '-',
                                    round((float) ($reqItem?->quantity ?? 0), 2),
                                    $toUsd($reqItem?->unit_price ?? 0),       // V. UN
                                    $toUsd($item->initial_value),             // V. Inicial
                                    $toUsd($item->partial_value),             // V. Parcial
                                    $toUsd($totalApportionment),              // Rateio
                                    round((float) ($percentage * 100), 2),    // A porcentagem de rateio não sofre conversão cambial
                                    $toUsd($item->final_value),               // V. Final
                                ]));
                            }

                            $writer->close();
                        }, $fileName, [
                            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]);
                    }),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(\App\Filament\Exports\SettlementExporter::class)
                    ->label('Exportar Resumo (Excel)'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettlements::route('/'),
            'create' => Pages\CreateSettlement::route('/create'),
            'edit' => Pages\EditSettlement::route('/{record}/edit'),
        ];
    }
}
