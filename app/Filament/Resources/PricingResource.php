<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PricingResource\Pages;
use App\Models\Pricing;
use App\Models\Settlement;
use App\Models\SettlementItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;

class PricingResource extends Resource
{
    protected static ?string $model = Pricing::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Precificações';
    protected static ?string $modelLabel = 'Precificação';
    protected static ?string $pluralModelLabel = 'Precificações';
    protected static ?string $navigationGroup = 'Comercial';
    protected static ?int $navigationSort = 2;

    /**
     * Parse Inteligente: Lê números no formato Brasileiro (1.234,56) ou Americano (1234.56).
     * Aceita digitação livre com ponto ou vírgula nos campos do formulário.
     */
    public static function parseNumber($val): float
    {
        if (is_numeric($val))
            return (float) $val;

        $val = (string) $val;

        // Se a string tem ponto E vírgula (Ex: 1.234,56), removemos o ponto de milhar primeiro
        if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
            $val = str_replace('.', '', $val);
        }

        // Agora trocamos a vírgula decimal pelo ponto (padrão do PHP)
        $val = str_replace(',', '.', $val);

        // Limpa qualquer outra letra ou símbolo que não seja dígito, ponto ou sinal de menos
        $val = preg_replace('/[^0-9.-]/', '', $val);

        return (float) $val;
    }

    /**
     * Motor de cálculo centralizado para precificação.
     */
    public static function calculateItem(array $itemData, float $globalMargin, ?float $manualPrice = null): array
    {
        $totalCostUsd = self::parseNumber($itemData['total_cost'] ?? 0);
        $qtySent = self::parseNumber($itemData['quantity_sent'] ?? 1);
        $boxFactor = self::parseNumber($itemData['box_factor'] ?? 1);
        $isFractional = (bool) ($itemData['is_fractional'] ?? false);

        if ($qtySent <= 0)
            $qtySent = 1;
        if ($boxFactor <= 0)
            $boxFactor = 1;

        // 1. Calcula o Custo Base (Caixa fechada) em USD
        $baseCost = $totalCostUsd / $qtySent;

        // 2. Calcula Custo UN (USD) dependendo se a venda é fracionada
        $unitCost = $isFractional ? ($baseCost / $boxFactor) : $baseCost;
        $itemData['unit_cost'] = round($unitCost, 4);

        // 3. Aplica regras de Preço e Margem
        if ($manualPrice !== null) {
            $itemData['suggested_price'] = $manualPrice;
            if ($manualPrice > 0) {
                // Cálculo de markup reverso
                $itemData['profit_margin'] = round((($manualPrice - $unitCost) / $manualPrice) * 100, 2);
            } else {
                $itemData['profit_margin'] = 0;
            }
        } else {
            $marginDec = $globalMargin / 100;
            if ($marginDec >= 1)
                $marginDec = 0.99;

            $suggestedPrice = $unitCost / (1 - $marginDec);
            $itemData['suggested_price'] = round($suggestedPrice, 2);
            $itemData['profit_margin'] = $globalMargin;
        }

        return $itemData;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Definições Globais')
                    ->schema([
                        Forms\Components\Select::make('settlement_id')
                            ->label('Fechamento (Base de Custos)')
                            ->options(Settlement::with('request')->get()->pluck('request.observation', 'id'))
                            ->required()
                            ->disabledOn('edit')
                            ->searchable()
                            ->default(request()->query('settlement_id'))
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if (!$state) {
                                    $set('items', []);
                                    $set('usd_quote', null);
                                    return;
                                }

                                $settlement = Settlement::with(['items.requestItem.product'])->find($state);

                                $usdQuote = $settlement?->usd_quote ?? 1;
                                if ($usdQuote <= 0)
                                    $usdQuote = 1;

                                $set('usd_quote', $usdQuote);

                                $items = [];
                                $margin = (float) ($get('ideal_margin') ?? 30);

                                foreach ($settlement->items as $sItem) {
                                    $reqItem = $sItem->requestItem;
                                    $product = $reqItem?->product;

                                    $totalCostUsd = $sItem->final_value / $usdQuote;

                                    $itemRaw = [
                                        'settlement_item_id' => $sItem->id,
                                        'winthor_code' => $reqItem?->winthor_code ?? $product?->codprod ?? '-',
                                        'product_name' => $reqItem?->product_name ?? '-',
                                        'total_cost' => $totalCostUsd,
                                        'quantity_sent' => $reqItem?->quantity ?? 1,
                                        'box_factor' => $reqItem?->qtunitcx ?? $product?->qtunitcx ?? 1,
                                        'is_fractional' => false,
                                    ];

                                    $items[] = self::calculateItem($itemRaw, $margin, null);
                                }
                                $set('items', $items);
                            })
                            ->columnSpan(['default' => 12, 'md' => 5]),

                        Forms\Components\TextInput::make('usd_quote')
                            ->label('Cot. USD Global (Base)')
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('R$')
                            ->formatStateUsing(fn($state) => $state ? number_format((float) $state, 4, ',', '.') : '')
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, ?Pricing $record) {
                                if ($record && $record->settlement) {
                                    $component->state($record->settlement->usd_quote);
                                }
                            })
                            ->columnSpan(['default' => 12, 'md' => 3]),

                        Forms\Components\TextInput::make('ideal_margin')
                            ->label('Margem Ideal Global (%)')
                            ->numeric()
                            ->default(40)
                            ->suffix('%')
                            ->required()
                            ->live(debounce: 800)
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $items = $get('items') ?? [];
                                $margin = (float) $state;

                                foreach ($items as $index => $itemData) {
                                    $items[$index] = self::calculateItem($itemData, $margin, null);
                                }
                                $set('items', $items);
                            })
                            ->extraInputAttributes([
                                'x-on:keydown.enter.prevent' => '
                                    let inputs = Array.from($el.closest("form").querySelectorAll("input:not([disabled]):not([readonly]):not([type=hidden])"));
                                    let index = inputs.indexOf($el);
                                    if (index > -1 && index < inputs.length - 1) {
                                        inputs[index + 1].focus();
                                        inputs[index + 1].select();
                                    }
                                '
                            ])
                            ->columnSpan(['default' => 12, 'md' => 4]),
                    ])->columns(12),

                Forms\Components\Section::make('Precificação dos Produtos (Valores em US$)')
                    ->schema([
                        TableRepeater::make('items')
                            ->relationship('items')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->afterStateHydrated(function (Forms\Components\Component $component, ?Pricing $record, $state) {
                                if (!$record || empty($state))
                                    return;

                                $record->loadMissing('items.settlementItem.requestItem.product', 'settlement');

                                $usdQuote = $record->settlement?->usd_quote ?? 1;
                                if ($usdQuote <= 0)
                                    $usdQuote = 1;

                                foreach ($state as $uuid => $itemData) {
                                    $dbItem = $record->items->firstWhere('id', $itemData['id'] ?? null);
                                    if ($dbItem && $dbItem->settlementItem) {
                                        $sItem = $dbItem->settlementItem;
                                        $req = $sItem->requestItem;

                                        $state[$uuid]['winthor_code'] = $req->winthor_code ?? $req->product?->codprod ?? '-';
                                        $state[$uuid]['product_name'] = $req->product_name ?? '-';
                                        $state[$uuid]['total_cost'] = $sItem->final_value / $usdQuote;
                                        $state[$uuid]['quantity_sent'] = $req->quantity ?? 1;
                                        $state[$uuid]['box_factor'] = $req->qtunitcx ?? $req->product?->qtunitcx ?? 1;
                                    }
                                }
                                $component->state($state);
                            })
                            ->colStyles([
                                'winthor_code' => 'width: 120px;',
                                'product_name' => 'width: 600px; white-space: normal;',
                                'total_cost' => 'width: 200px;',
                                'quantity_sent' => 'width: 150px;',
                                'box_factor' => 'width: 110px;',
                                'unit_cost' => 'width: 150px;',
                                'suggested_price' => 'width: 180px;',
                                'profit_margin' => 'width: 180px;',
                                'is_fractional' => 'width: 70px; text-align: center; vertical-align: middle;',
                            ])
                            ->schema([
                                Forms\Components\Hidden::make('settlement_item_id'),

                                Forms\Components\TextInput::make('winthor_code')
                                    ->label('Cód.')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->extraInputAttributes(['class' => 'text-sm']),

                                Forms\Components\TextInput::make('product_name')
                                    ->label('Produto')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->extraInputAttributes(['class' => 'text-sm leading-tight']),

                                Forms\Components\TextInput::make('total_cost')
                                    ->label('Custo Total')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->prefix('US$')
                                    ->formatStateUsing(fn($state) => number_format((float) $state, 4, ',', '.'))
                                    ->extraInputAttributes(['class' => 'text-sm']),

                                Forms\Components\TextInput::make('quantity_sent')
                                    ->label('QTD')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn($state) => number_format((float) $state, 2, ',', ''))
                                    ->extraInputAttributes(['class' => 'bg-green-50 text-green-700 font-bold border-green-200 text-center text-sm']),

                                Forms\Components\TextInput::make('box_factor')
                                    ->label('Fator CX')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn($state) => number_format((float) $state, 2, ',', ''))
                                    ->extraInputAttributes(['class' => 'text-center text-sm']),

                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Custo Un')
                                    ->disabled()
                                    ->prefix('US$')
                                    ->formatStateUsing(fn($state) => number_format((float) $state, 4, ',', '.'))
                                    ->dehydrateStateUsing(fn($state) => self::parseNumber($state))
                                    ->extraInputAttributes(fn(Get $get) => [
                                        'class' => ($get('is_fractional')
                                            ? 'bg-warning-100 text-warning-800 font-extrabold border-warning-300 '
                                            : 'bg-info-50 text-info-800 font-bold border-info-200 ') . 'text-sm'
                                    ]),

                                Forms\Components\TextInput::make('suggested_price')
                                    ->label('Preço Venda')
                                    ->prefix('US$')
                                    ->live(debounce: 800)
                                    ->extraInputAttributes([
                                        'inputmode' => 'decimal',
                                        'class' => 'text-sm alvo-preco',
                                        // Pula para a Margem da mesma linha
                                        'x-on:keydown.enter.prevent' => '
                                            let row = $el.closest("tr");
                                            if (row) {
                                                let nextInput = row.querySelector(".alvo-margem");
                                                if (nextInput) {
                                                    nextInput.focus();
                                                    nextInput.select();
                                                }
                                            }
                                        '
                                    ])
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $margin = (float) ($get('../../ideal_margin') ?? 30);
                                        $price = self::parseNumber($state);

                                        $data = [
                                            'total_cost' => $get('total_cost'),
                                            'quantity_sent' => $get('quantity_sent'),
                                            'box_factor' => $get('box_factor'),
                                            'is_fractional' => $get('is_fractional'),
                                        ];

                                        $updatedData = self::calculateItem($data, $margin, $price);
                                        $set('profit_margin', $updatedData['profit_margin']);
                                    }),

                                Forms\Components\TextInput::make('profit_margin')
                                    ->label('Margem Lucro (%)')
                                    ->live(debounce: 800)
                                    ->suffix('%')
                                    ->formatStateUsing(fn($state) => $state !== null ? number_format((float) $state, 2, ',', '.') : '')
                                    ->dehydrateStateUsing(fn($state) => self::parseNumber($state))
                                    ->extraInputAttributes([
                                        'inputmode' => 'decimal',
                                        'class' => 'bg-gray-50 font-bold text-gray-700 text-center text-sm alvo-margem',
                                        // Pula para o Preço da linha de baixo
                                        'x-on:keydown.enter.prevent' => '
                                            let tbody = $el.closest("tbody");
                                            if (tbody) {
                                                let rows = Array.from(tbody.querySelectorAll("tr"));
                                                let currentRow = $el.closest("tr");
                                                let index = rows.indexOf(currentRow);
                                                if (index > -1 && index < rows.length - 1) {
                                                    let nextInput = rows[index + 1].querySelector(".alvo-preco");
                                                    if (nextInput) {
                                                        nextInput.focus();
                                                        nextInput.select();
                                                    }
                                                }
                                            }
                                        '
                                    ])
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        // AQUI OCORRE A MÁGICA INVERSA
                                        $manualMargin = self::parseNumber($state);

                                        $data = [
                                            'total_cost' => $get('total_cost'),
                                            'quantity_sent' => $get('quantity_sent'),
                                            'box_factor' => $get('box_factor'),
                                            'is_fractional' => $get('is_fractional'),
                                        ];

                                        $updatedData = self::calculateItem($data, $manualMargin, null);
                                        $set('suggested_price', $updatedData['suggested_price']);
                                        $set('profit_margin', $updatedData['profit_margin']);
                                    }),

                                Forms\Components\Checkbox::make('is_fractional')
                                    ->label('V.F.')
                                    ->live()
                                    ->extraAttributes(['class' => 'flex justify-center items-center pt-2'])
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        // Resgata a margem atual do campo para não resetar a edição manual do usuário
                                        $currentMargin = self::parseNumber($get('profit_margin'));
                                        if (!$currentMargin || $currentMargin <= 0) {
                                            $currentMargin = (float) ($get('../../ideal_margin') ?? 30);
                                        }

                                        $data = [
                                            'total_cost' => $get('total_cost'),
                                            'quantity_sent' => $get('quantity_sent'),
                                            'box_factor' => $get('box_factor'),
                                            'is_fractional' => $state,
                                        ];

                                        $updatedData = self::calculateItem($data, $currentMargin, null);

                                        $set('unit_cost', $updatedData['unit_cost']);
                                        $set('suggested_price', $updatedData['suggested_price']);
                                        $set('profit_margin', $updatedData['profit_margin']);
                                    }),
                            ])
                            ->columnSpanFull()
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('settlement.request.observation')
                    ->label('Solicitação Vinculada')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ideal_margin')
                    ->label('Margem Ideal')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Qtd. Itens')
                    ->counts('items')
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data de Criação')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Filtros
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPricings::route('/'),
            'create' => Pages\CreatePricing::route('/create'),
            'edit' => Pages\EditPricing::route('/{record}/edit'),
        ];
    }
}