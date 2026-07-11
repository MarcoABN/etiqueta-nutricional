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
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;

class SettlementResource extends Resource
{
    protected static ?string $model = Settlement::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Fechamentos';
    protected static ?string $modelLabel = 'Fechamento';
    protected static ?string $pluralModelLabel = 'Fechamentos';
    protected static ?string $navigationGroup = 'Comercial';
    protected static ?int $navigationSort = 1;

    public static function updateTotals(Get $get, Set $set): void
    {
        $requestId = $get('request_id') ?? $get('../../request_id');
        if (!$requestId)
            return;

        $request = Request::with('items')->find($requestId);
        if (!$request)
            return;

        $factorRaw = $get('calculation_factor') ?? $get('../../calculation_factor');
        $factor = (float) ($factorRaw ?: 100);
        $factorDec = $factor > 0 ? $factor / 100 : 1.00;

        $initialTotal = 0;
        $totalValue = 0;

        foreach ($request->items as $item) {
            $initial = ($item->quantity ?? 0) * ($item->unit_price ?? 0);
            $initialTotal += $initial;

            $partial = $factorDec > 0 ? $initial / $factorDec : 0;
            $totalValue += $partial;
        }

        $expenses = $get('expenses') ?? $get('../../expenses') ?? [];
        $internalExpenses = 0;
        $externalExpenses = 0;

        foreach ($expenses as $exp) {
            $val = (float) ($exp['amount'] ?? 0);
            if (!empty($exp['is_external'])) {
                $externalExpenses += $val;
            } else {
                $internalExpenses += $val;
            }
        }

        $totalExpenses = $internalExpenses + $externalExpenses;

        $expensePercentage = $totalValue > 0 ? ($totalExpenses / $totalValue) * 100 : 0;
        $overallTotal = $totalValue + $totalExpenses;

        $isRepeater = $get('request_id') === null;
        $prefix = $isRepeater ? '../../' : '';

        // Campos reais salvos no banco
        $set($prefix . 'initial_total', round($initialTotal, 2));
        $set($prefix . 'total_value', round($totalValue, 2));
        $set($prefix . 'total_expenses', round($totalExpenses, 2));
        $set($prefix . 'expense_percentage', round($expensePercentage, 3));
        $set($prefix . 'overall_total', round($overallTotal, 2));

        // Campos de exibição visual com máscara no padrão Brasileiro
        $set($prefix . 'display_initial_total', number_format($initialTotal, 2, ',', '.'));
        $set($prefix . 'display_total_value', number_format($totalValue, 2, ',', '.'));
        $set($prefix . 'display_total_expenses', number_format($totalExpenses, 2, ',', '.'));
        $set($prefix . 'display_internal_expenses', number_format($internalExpenses, 2, ',', '.'));
        $set($prefix . 'display_external_expenses', number_format($externalExpenses, 2, ',', '.'));
        $set($prefix . 'display_overall_total', number_format($overallTotal, 2, ',', '.'));
        $set($prefix . 'display_expense_percentage', number_format($expensePercentage, 3, ',', '.'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('show_in_usd')->default(false)->dehydrated(false),
                Forms\Components\Hidden::make('is_factor_unlocked')->default(false)->dehydrated(false),
                Forms\Components\Hidden::make('initial_total'),
                Forms\Components\Hidden::make('total_value'),
                Forms\Components\Hidden::make('total_expenses'),
                Forms\Components\Hidden::make('expense_percentage'),
                Forms\Components\Hidden::make('overall_total'),

                Forms\Components\Section::make('Dados e Totais do Fechamento')
                    ->disabled(fn(?Settlement $record) => $record?->is_locked ?? false)
                    ->schema([
                        Forms\Components\Grid::make(['default' => 1, 'sm' => 2, 'lg' => 20])
                            ->schema([
                                Forms\Components\Select::make('request_id')
                                    ->label('Solicitação')
                                    ->options(Request::pluck('observation', 'id'))
                                    ->required()
                                    ->unique(ignoreRecord: true, modifyRuleUsing: fn(Unique $rule) => $rule->whereNull('deleted_at'))
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Não é permitido criar mais de um fechamento por solicitação.')
                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 5]),

                                Forms\Components\TextInput::make('usd_quote')
                                    ->label('Cotação USD')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->minValue(0)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                                    ->prefixAction(
                                        Forms\Components\Actions\Action::make('toggle_currency')
                                            ->label('US$')
                                            ->icon(fn(Get $get) => $get('show_in_usd') ? 'heroicon-m-currency-dollar' : 'heroicon-m-arrows-right-left')
                                            ->color(fn(Get $get) => $get('show_in_usd') ? 'success' : 'gray')
                                            ->tooltip('Alternar exibição de conversão do Dólar')
                                            ->disabled(fn(Get $get) => !(floatval($get('usd_quote')) > 0))
                                            ->action(function (Set $set, Get $get) {
                                                $set('show_in_usd', !$get('show_in_usd'));
                                            })
                                    )
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Cotação padrão. Usada para itens e despesas sem cotação específica.')
                                    ->columnSpan(['default' => 1, 'sm' => 1, 'lg' => 3]),

                                Forms\Components\TextInput::make('calculation_factor')
                                    ->label('Fator(%)')
                                    ->numeric()
                                    ->default(100)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                                    ->disabled(fn(Get $get, ?Settlement $record) => ($record?->is_locked ?? false) || !$get('is_factor_unlocked'))
                                    ->hintAction(
                                        Forms\Components\Actions\Action::make('unlock_factor')
                                            ->icon('heroicon-m-lock-open')
                                            ->label('')
                                            ->requiresConfirmation()
                                            ->modalHeading('Alterar Fator de Cálculo?')
                                            ->modalDescription('O fator padrão agora é 100%. Alterar este valor modificará o cálculo base de rateio, ativando a regra antiga (ex: a NF representa apenas X% do custo real). Deseja realmente alterar?')
                                            ->hidden(fn(Get $get, ?Settlement $record) => ($record?->is_locked ?? false) || $get('is_factor_unlocked'))
                                            ->action(fn(Set $set) => $set('is_factor_unlocked', true))
                                    )
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Porcentagem usada para retornar ao valor original.')
                                    ->columnSpan(['default' => 1, 'sm' => 1, 'lg' => 3]),

                                Forms\Components\TextInput::make('display_expense_percentage')
                                    ->label('% Despesa')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->expense_percentage, 3, ',', '.') : '0,000'))
                                    ->suffix('%')
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Representatividade das despesas sobre o Total Real.')
                                    ->columnSpan(['default' => 1, 'sm' => 1, 'lg' => 3]),

                                Forms\Components\TextInput::make('display_internal_expenses')
                                    ->label('Desp. Internas')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->expenses()->where('is_external', false)->sum('amount'), 2, ',', '.') : '0,00'))
                                    ->prefix('R$')
                                    ->suffix(function (Get $get): string {
                                        $isUsd = (bool) $get('show_in_usd');
                                        if (!$isUsd) return '';
                                        $expenses = $get('expenses') ?? [];
                                        $globalQuote = (float) $get('usd_quote');
                                        $totalUsd = 0;
                                        foreach ($expenses as $exp) {
                                            if (!empty($exp['is_external'])) continue;
                                            $val = (float) ($exp['amount'] ?? 0);
                                            $useCustom = (bool) ($exp['use_custom_quote'] ?? false);
                                            $customQuote = (float) ($exp['custom_usd_quote'] ?? 0);
                                            $quoteToUse = ($useCustom && $customQuote > 0) ? $customQuote : $globalQuote;
                                            if ($quoteToUse > 0) $totalUsd += ($val / $quoteToUse);
                                        }
                                        return $totalUsd > 0 ? '≈ US$ ' . number_format($totalUsd, 2, ',', '.') : '';
                                    })
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Soma das despesas com flag Externa desmarcada.')
                                    ->columnSpan(['default' => 1, 'sm' => 1, 'lg' => 3]),

                                Forms\Components\TextInput::make('display_external_expenses')
                                    ->label('Desp. Externas')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->expenses()->where('is_external', true)->sum('amount'), 2, ',', '.') : '0,00'))
                                    ->prefix('R$')
                                    ->suffix(function (Get $get): string {
                                        $isUsd = (bool) $get('show_in_usd');
                                        if (!$isUsd) return '';
                                        $expenses = $get('expenses') ?? [];
                                        $globalQuote = (float) $get('usd_quote');
                                        $totalUsd = 0;
                                        foreach ($expenses as $exp) {
                                            if (empty($exp['is_external'])) continue;
                                            $val = (float) ($exp['amount'] ?? 0);
                                            $useCustom = (bool) ($exp['use_custom_quote'] ?? false);
                                            $customQuote = (float) ($exp['custom_usd_quote'] ?? 0);
                                            $quoteToUse = ($useCustom && $customQuote > 0) ? $customQuote : $globalQuote;
                                            if ($quoteToUse > 0) $totalUsd += ($val / $quoteToUse);
                                        }
                                        return $totalUsd > 0 ? '≈ US$ ' . number_format($totalUsd, 2, ',', '.') : '';
                                    })
                                    ->helperText(' ')
                                    ->hintIcon('heroicon-m-question-mark-circle')
                                    ->hintIconTooltip('Soma das despesas marcadas como Externa.')
                                    ->columnSpan(['default' => 1, 'sm' => 1, 'lg' => 3]),
                            ]),

                        Forms\Components\Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                Forms\Components\TextInput::make('display_initial_total')
                                    ->label('Total Inicial')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->items()->sum('initial_value'), 2, ',', '.') : '0,00'))
                                    ->prefix('R$')
                                    ->suffix(function (Get $get): string {
                                        $isUsd = (bool) $get('show_in_usd');
                                        $quote = (float) $get('usd_quote');
                                        $val = (float) $get('initial_total');
                                        if ($isUsd && $quote > 0 && $val > 0) {
                                            return '≈ US$ ' . number_format($val / $quote, 2, ',', '.');
                                        }
                                        return '';
                                    })
                                    ->extraInputAttributes(['class' => 'text-lg font-bold text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/30', 'style' => '-webkit-text-fill-color: currentcolor; opacity: 1;'])
                                    ->helperText(' ')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('display_total_value')
                                    ->label('Total Real (Produtos)')
                                    ->hidden(fn(Get $get) => (float) ($get('calculation_factor') ?: 100) == 100)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->total_value, 2, ',', '.') : '0,00'))
                                    ->prefix('R$')
                                    ->suffix(function (Get $get): string {
                                        $isUsd = (bool) $get('show_in_usd');
                                        $quote = (float) $get('usd_quote');
                                        $val = (float) $get('total_value');
                                        if ($isUsd && $quote > 0 && $val > 0) {
                                            return '≈ US$ ' . number_format($val / $quote, 2, ',', '.');
                                        }
                                        return '';
                                    })
                                    ->extraInputAttributes(['class' => 'text-lg font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30', 'style' => '-webkit-text-fill-color: currentcolor; opacity: 1;'])
                                    ->helperText(' ')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('display_total_expenses')
                                    ->label('Despesas (Soma Geral)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->total_expenses, 2, ',', '.') : '0,00'))
                                    ->prefix('R$')
                                    ->suffix(function (Get $get): string {
                                        $isUsd = (bool) $get('show_in_usd');
                                        if (!$isUsd) return '';

                                        $expenses = $get('expenses') ?? [];
                                        $globalQuote = (float) $get('usd_quote');
                                        $totalUsd = 0;
                                        foreach ($expenses as $exp) {
                                            $val = (float) ($exp['amount'] ?? 0);
                                            $useCustom = (bool) ($exp['use_custom_quote'] ?? false);
                                            $customQuote = (float) ($exp['custom_usd_quote'] ?? 0);
                                            $quoteToUse = ($useCustom && $customQuote > 0) ? $customQuote : $globalQuote;
                                            if ($quoteToUse > 0) {
                                                $totalUsd += ($val / $quoteToUse);
                                            }
                                        }
                                        return $totalUsd > 0 ? '≈ US$ ' . number_format($totalUsd, 2, ',', '.') : '';
                                    })
                                    ->extraInputAttributes(['class' => 'text-lg font-bold text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/30', 'style' => '-webkit-text-fill-color: currentcolor; opacity: 1;'])
                                    ->helperText(' ')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('display_overall_total')
                                    ->label('Total Final (Produtos + Despesas)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn($component, ?Settlement $record) => $component->state($record ? number_format($record->total_value + $record->total_expenses, 2, ',', '.') : '0,00'))
                                    ->prefix('R$')
                                    ->suffix(function (Get $get): string {
                                        $isUsd = (bool) $get('show_in_usd');
                                        $quote = (float) $get('usd_quote');
                                        $valProd = (float) $get('total_value');

                                        $totalUsd = 0;
                                        if ($isUsd && $quote > 0) {
                                            $totalUsd += ($valProd / $quote);
                                            $expenses = $get('expenses') ?? [];
                                            foreach ($expenses as $exp) {
                                                $val = (float) ($exp['amount'] ?? 0);
                                                $useCustom = (bool) ($exp['use_custom_quote'] ?? false);
                                                $customQuote = (float) ($exp['custom_usd_quote'] ?? 0);
                                                $quoteToUse = ($useCustom && $customQuote > 0) ? $customQuote : $quote;
                                                if ($quoteToUse > 0) {
                                                    $totalUsd += ($val / $quoteToUse);
                                                }
                                            }
                                        }

                                        return $totalUsd > 0 ? '≈ US$ ' . number_format($totalUsd, 2, ',', '.') : '';
                                    })
                                    ->extraInputAttributes(['class' => 'text-2xl bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-300 font-extrabold', 'style' => '-webkit-text-fill-color: currentcolor; opacity: 1; padding-top: 1rem; padding-bottom: 1rem; height: auto;'])
                                    ->helperText(' ')
                                    ->columnSpan(1),
                            ])->extraAttributes(['class' => 'mt-2 border-t border-gray-200 dark:border-white/10 pt-4']),
                    ]),

                Forms\Components\Section::make('Despesas do Fechamento')
                    ->disabled(fn(?Settlement $record) => $record?->is_locked ?? false)
                    ->schema([
                        TableRepeater::make('expenses')
                            ->relationship('expenses')
                            ->orderColumn('expense_number')
                            ->hiddenLabel()
                            ->addActionLabel('Adicionar Despesa')
                            ->reorderableWithButtons()
                            ->reorderableWithDragAndDrop(false)
                            ->reorderable(true)
                            ->colStyles([
                                'description' => 'width: 100%; vertical-align: middle;',
                                'amount' => 'width: 250px; min-width: 250px; vertical-align: middle;',
                                'custom_usd_quote' => 'width: 120px; min-width: 120px; vertical-align: middle;',
                                'use_custom_quote' => 'width: 45px; min-width: 45px; vertical-align: middle; text-align: center;',
                                'is_external' => 'width: 60px; min-width: 60px; vertical-align: middle; text-align: center;',
                            ])
                            ->schema([
                                Forms\Components\TextInput::make('description')
                                    ->label('Descrição da Despesa')
                                    ->placeholder('Ex: Frete Marítimo, Pallets...')
                                    ->extraInputAttributes(fn(Get $get) => [
                                        'class' => $get('is_external') ? 'bg-orange-50 dark:bg-orange-900/20' : ''
                                    ])
                                    ->required(),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Valor (R$)')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                                    ->extraInputAttributes(fn(Get $get) => [
                                        'class' => $get('is_external') ? 'bg-orange-50 dark:bg-orange-900/20' : ''
                                    ])
                                    ->suffix(function (Get $get): string {
                                        $isUsd = (bool) $get('../../show_in_usd');
                                        $val = (float) $get('amount');

                                        if ($isUsd && $val > 0) {
                                            $globalQuote = (float) $get('../../usd_quote');
                                            $useCustom = (bool) $get('use_custom_quote');
                                            $customQuote = (float) $get('custom_usd_quote');

                                            $quoteToUse = ($useCustom && $customQuote > 0) ? $customQuote : ($customQuote > 0 ? $customQuote : $globalQuote);

                                            if ($quoteToUse > 0) {
                                                return '≈ US$ ' . number_format($val / $quoteToUse, 2, ',', '.');
                                            }
                                        }
                                        return '';
                                    }),

                                Forms\Components\TextInput::make('custom_usd_quote')
                                    ->label('Cotação')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->maxValue(99.9999)
                                    ->disabled(fn(Get $get) => !$get('use_custom_quote'))
                                    ->dehydrated()
                                    ->formatStateUsing(function ($state, Get $get) {
                                        if (!$get('use_custom_quote')) {
                                            return $get('../../usd_quote');
                                        }
                                        return $state;
                                    })
                                    ->extraInputAttributes(fn(Get $get) => [
                                        'maxlength' => 7,
                                        'class' => 'text-right px-1 ' . ($get('is_external') ? 'bg-orange-50 dark:bg-orange-900/20' : '')
                                    ])
                                    ->required(fn(Get $get) => (bool) $get('use_custom_quote'))
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotals($get, $set)),

                                Forms\Components\Checkbox::make('use_custom_quote')
                                    ->label('')
                                    ->extraAttributes(['class' => 'flex justify-center items-center pt-2'])
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        if ($state) {
                                            $set('custom_usd_quote', $get('../../usd_quote'));
                                        } else {
                                            $set('custom_usd_quote', null);
                                        }
                                        self::updateTotals($get, $set);
                                    }),

                                Forms\Components\Toggle::make('is_external')
                                    ->label('Externa?')
                                    ->default(false)
                                    ->live()
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
                            <span x-data="{}" x-tooltip="\'O rateio das despesas é calculado de acordo com a % que o valor partial de cada produto representa do total Real.\'">
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
                Tables\Columns\TextColumn::make('request.observation')
                    ->label('Solicitação')
                    ->limit(30)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('usd_quote')
                    ->label('Cot. USD')
                    ->formatStateUsing(fn($state) => 'US$ ' . number_format((float) $state, 4, ',', '.'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_value')->label('Total Real')->money('BRL')->sortable(),

                Tables\Columns\TextColumn::make('overall_total')
                    ->label('Total Final')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_expenses')->label('Despesas')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('expense_percentage')->label('% Despesa')->suffix('%')->sortable(),
                Tables\Columns\IconColumn::make('is_locked')
                    ->label('Consolidado')
                    ->boolean()
                    ->trueIcon('heroicon-s-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn($state) => $state ? 'Fechamento Consolidado (Somente Leitura)' : 'Fechamento Aberto para Edição')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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