<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\Request;
use App\Models\RequestItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Widgets\Widget;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Carbon;

class RequestItemsWidget extends Widget implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $view = 'livewire.request-items-widget';
    protected int | string | array $columnSpan = 'full';

    public ?Request $record = null;
    public ?string $editingItemId = null;

    public $product_id;
    public $product_name;
    public $quantity = 1;
    public $packaging = 'CX';
    public $unit_price;
    public $observation;

    // Variáveis do produto
    public $pesoliq;
    public $unidade;
    public $qtunitcx;

    // Novas variáveis para o modo NF (Peso)
    public $is_weight_mode = false;
    public $nf_weight;
    public $nf_total;

    // Helper para verificar se está travado
    protected function isLocked(): bool
    {
        return $this->record->is_locked || ($this->record->settlement?->is_locked ?? false);
    }

    public function form(Form $form): Form
    {
        $calcNf = function (Forms\Get $get, Forms\Set $set) {

            $parseNumber = function ($val) {
                $val = (string) $val;
                if (str_contains($val, ',')) {
                    $val = str_replace('.', '', $val);
                    $val = str_replace(',', '.', $val);
                }
                return (float) $val;
            };

            $weight = $parseNumber($get('nf_weight'));
            $total = $parseNumber($get('nf_total'));
            $pesoliq = $parseNumber($get('pesoliq'));
            $qtunitcx = $parseNumber($get('qtunitcx'));

            if ($weight > 0 && $pesoliq > 0 && $qtunitcx > 0) {
                $units = $weight / $pesoliq;
                $boxes = $units / $qtunitcx;

                $set('quantity', round($boxes, 4));

                if ($boxes > 0 && $total > 0) {
                    $unitPrice = $total / $boxes;
                    $set('unit_price', round($unitPrice, 4));
                } else {
                    $set('unit_price', null);
                }
            }
        };

        return $form
            ->schema([
                Forms\Components\Section::make(fn() => $this->editingItemId ? 'Editar Item' : 'Adicionar Novo Item')
                    ->compact()
                    ->disabled(fn() => $this->isLocked()) // <--- TRAVA DO FORMULÁRIO AQUI
                    ->schema([
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Buscar Produto')
                                    ->placeholder('Digite Nome ou Código...')
                                    ->searchable()
                                    ->live()
                                    ->getSearchResultsUsing(function (string $search) {
                                        return Product::query()
                                            ->where(function ($query) use ($search) {
                                                $query->where('product_name', 'ilike', "%{$search}%")
                                                    ->orWhereRaw("CAST(codprod AS TEXT) ILIKE ?", ["%{$search}%"])
                                                    ->orWhere('barcode', 'ilike', "%{$search}%");
                                            })
                                            ->orderByRaw("
                                                CASE 
                                                    WHEN CAST(codprod AS TEXT) = ? THEN 0 
                                                    WHEN CAST(codprod AS TEXT) ILIKE ? THEN 1 
                                                    ELSE 2 
                                                END
                                            ", [$search, "{$search}%"])
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn($p) => [$p->id => "{$p->codprod} - {$p->product_name}"]);
                                    })
                                    ->getOptionLabelUsing(fn($value): ?string => Product::find($value)?->product_name)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($product = Product::find($state)) {
                                            $set('product_name', $product->product_name);
                                            $set('packaging', $get('packaging') ?: 'CX');
                                            $set('pesoliq', $product->pesoliq);
                                            $set('unidade', $product->unidade);
                                            $set('qtunitcx', $product->qtunitcx);
                                        } else {
                                            $set('product_name', null);
                                            $set('pesoliq', null);
                                            $set('unidade', null);
                                            $set('qtunitcx', null);
                                        }
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 4, 'lg' => 3]),

                                Forms\Components\TextInput::make('product_name')
                                    ->label('Descrição do Item')
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 8, 'lg' => 4]),

                                Forms\Components\TextInput::make('pesoliq')
                                    ->label('Peso Líq')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(['default' => 4, 'lg' => 2]),

                                Forms\Components\TextInput::make('unidade')
                                    ->label('Unidade')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(['default' => 4, 'lg' => 1]),

                                Forms\Components\TextInput::make('qtunitcx')
                                    ->label('Qtd/CX')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(['default' => 4, 'lg' => 2]),

                                Forms\Components\Toggle::make('is_weight_mode')
                                    ->label('NF (Peso)')
                                    ->live()
                                    ->inline(false)
                                    ->afterStateUpdated($calcNf)
                                    ->columnSpan(['default' => 3, 'md' => 2, 'lg' => 1]),

                                Forms\Components\TextInput::make('observation')
                                    ->label('Observação do Produto')
                                    ->columnSpan(['default' => 9, 'md' => 10, 'lg' => 3]),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Qtd')
                                    ->numeric()
                                    ->step('any')
                                    ->default(1)
                                    ->required()
                                    ->readOnly(fn(Forms\Get $get) => $get('is_weight_mode'))
                                    ->columnSpan(['default' => 6, 'md' => 2, 'lg' => 1]),

                                Forms\Components\Select::make('packaging')
                                    ->label('Emb')
                                    ->options(['CX' => 'CX', 'UN' => 'UN', 'DP' => 'DP', 'PCT' => 'PCT', 'FD' => 'FD'])
                                    ->default('CX')
                                    ->required()
                                    ->columnSpan(['default' => 6, 'md' => 2, 'lg' => 2]),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Valor UN(R$)')
                                    ->numeric()
                                    ->step('0.0001')
                                    ->prefix('R$')
                                    ->readOnly(fn(Forms\Get $get) => $get('is_weight_mode'))
                                    ->columnSpan(['default' => 12, 'md' => 4, 'lg' => 3]),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('save')
                                        ->label(fn() => $this->editingItemId ? 'ATUALIZAR' : 'INCLUIR')
                                        ->icon(fn() => $this->editingItemId ? 'heroicon-m-check' : 'heroicon-m-plus')
                                        ->color(fn() => $this->editingItemId ? 'warning' : 'primary')
                                        ->action(fn() => $this->saveItem()),

                                    Forms\Components\Actions\Action::make('cancel')
                                        ->label('CANCELAR')
                                        ->icon('heroicon-m-x-mark')
                                        ->color('gray')
                                        ->action(fn() => $this->resetInput())
                                        ->visible(fn() => $this->editingItemId !== null),
                                ])
                                    ->hidden(fn() => $this->isLocked()) // <--- ESCONDE BOTÕES SE TRAVADO
                                    ->columnSpan(['default' => 12, 'md' => 4, 'lg' => 2])
                                    ->extraAttributes(['class' => 'mt-8 flex justify-end gap-2'])
                                    ->alignRight(),

                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        Forms\Components\TextInput::make('nf_weight')
                                            ->label('Peso Total NF (Kg)')
                                            ->live(debounce: 500)
                                            ->afterStateUpdated($calcNf)
                                            ->columnSpan(['default' => 6, 'lg' => 3]),

                                        Forms\Components\TextInput::make('nf_total')
                                            ->label('Valor Total NF (R$)')
                                            ->prefix('R$')
                                            ->live(debounce: 500)
                                            ->afterStateUpdated($calcNf)
                                            ->columnSpan(['default' => 6, 'lg' => 3]),

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('refresh_data')
                                                ->label('')
                                                ->icon('heroicon-m-arrow-path')
                                                ->color('gray')
                                                ->tooltip('Recarregar os dados do produto (Peso Líq e Qtd/CX) e refazer cálculos')
                                                ->action(function (Forms\Get $get, Forms\Set $set) use ($calcNf) {
                                                    $prodId = $get('product_id');
                                                    if (!$prodId) return;

                                                    $product = Product::find($prodId);
                                                    if ($product) {
                                                        $set('pesoliq', $product->pesoliq);
                                                        $set('unidade', $product->unidade);
                                                        $set('qtunitcx', $product->qtunitcx);

                                                        $calcNf($get, $set);

                                                        Notification::make()->title('Dados do produto atualizados e recalculados!')->success()->send();
                                                    }
                                                })
                                        ])
                                            ->columnSpan(['default' => 12, 'lg' => 1])
                                            ->extraAttributes(['class' => 'mt-8 flex justify-center']),

                                        Forms\Components\Placeholder::make('info')
                                            ->hiddenLabel()
                                            ->content(fn(Forms\Get $get) => new HtmlString(
                                                "<div class='text-xs text-gray-500 mt-6'>" .
                                                    (!$get('pesoliq') || !$get('qtunitcx') ?
                                                        "<span class='text-danger-600 dark:text-danger-400'>⚠️ Falta Peso Líquido ou Qtd/CX. Ajuste no cadastro e clique no botão de recarregar.</span>" :
                                                        "ℹ️ Digite o Peso e o Valor da NF. O sistema calculará usando Peso Líq ({$get('pesoliq')}Kg) e Qtd/CX ({$get('qtunitcx')}).") .
                                                    "</div>"
                                            ))
                                            ->columnSpan(['default' => 12, 'lg' => 5]),
                                    ])
                                    ->visible(fn(Forms\Get $get) => $get('is_weight_mode'))
                                    ->columnSpanFull(),
                            ]),
                    ])
            ]);
    }

    public function saveItem()
    {
        // --- GUARD CLAUSE BACKEND ---
        if ($this->isLocked()) {
            Notification::make()->title('Ação não permitida')->body('Esta solicitação está fechada ou bloqueada.')->warning()->send();
            return;
        }

        $data = $this->form->getState();
        $prodId = $data['product_id'] ?? null;

        if ($prodId) {
            $exists = RequestItem::where('request_id', $this->record->id)
                ->where('product_id', $prodId)
                ->when($this->editingItemId, fn($q) => $q->where('id', '!=', $this->editingItemId))
                ->exists();

            if ($exists) {
                Notification::make()->title('Item Duplicado')->warning()->send();
                return;
            }
        }

        $itemData = [
            'request_id' => $this->record->id,
            'product_id' => $prodId,
            'product_name' => $data['product_name'],
            'quantity' => $data['quantity'],
            'packaging' => $data['packaging'],
            'unit_price' => $data['unit_price'],
            'observation' => $data['observation'],
            'winthor_code' => Product::find($prodId)?->codprod,
        ];

        if ($this->editingItemId) {
            RequestItem::find($this->editingItemId)->update($itemData);
            Notification::make()->title('Item atualizado')->success()->send();
        } else {
            RequestItem::create($itemData);
            Notification::make()->title('Item incluído')->success()->send();
        }

        $this->resetInput();
    }

    public function editItem($itemId)
    {
        $item = RequestItem::find($itemId);
        if (!$item) return;

        $this->editingItemId = $itemId;
        $product = Product::find($item->product_id);

        $this->form->fill([
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'quantity' => $item->quantity,
            'packaging' => $item->packaging,
            'unit_price' => $item->unit_price,
            'observation' => $item->observation,
            'pesoliq' => $product?->pesoliq,
            'unidade' => $product?->unidade,
            'qtunitcx' => $product?->qtunitcx,

            'is_weight_mode' => false,
            'nf_weight' => null,
            'nf_total' => null,
        ]);
    }

    public function resetInput()
    {
        $currentWeightMode = $this->is_weight_mode;

        $this->editingItemId = null;
        $this->form->fill([
            'product_id' => null,
            'product_name' => '',
            'quantity' => 1,
            'packaging' => 'CX',
            'unit_price' => null,
            'observation' => '',
            'pesoliq' => null,
            'unidade' => null,
            'qtunitcx' => null,

            'is_weight_mode' => $currentWeightMode,
            'nf_weight' => null,
            'nf_total' => null,
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RequestItem::query()
                    ->where('request_id', $this->record->id)
            )
            ->defaultSort('product_name', 'asc')
            ->heading('Itens Gravados')
            ->headerActions([
                Tables\Actions\Action::make('totals_display')
                    ->label(function () {
                        $items = RequestItem::where('request_id', $this->record->id)->get();

                        $totalVolumes = $items->sum('quantity');
                        $totalValue = $items->sum(fn($item) => $item->quantity * ($item->unit_price ?? 0));

                        $formattedVolumes = rtrim(rtrim(number_format($totalVolumes, 4, ',', '.'), '0'), ',');
                        if ($formattedVolumes === '') $formattedVolumes = '0';
                        $formattedValue = number_format($totalValue, 2, ',', '.');

                        return new HtmlString("
                            <span class='font-normal'>
                                <strong>Volumes:</strong> {$formattedVolumes} 
                                <span class='mx-2 text-gray-300 dark:text-gray-600'>|</span> 
                                <strong>Valor Total:</strong> R$ {$formattedValue}
                            </span>
                        ");
                    })
                    ->color('gray')
                    ->extraAttributes([
                        'class' => 'cursor-default pointer-events-none shadow-none bg-gray-50 dark:bg-gray-800',
                    ]),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('winthor_code')
                    ->label('Código')
                    ->default('Manual')
                    ->badge(fn($record) => empty($record->winthor_code))
                    ->color(fn($record) => empty($record->winthor_code) ? 'gray' : null)
                    ->sortable()
                    ->searchable()
                    ->alignCenter()
                    ->width('6rem'),

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produto')
                    ->weight('bold')
                    ->limit(45)
                    ->tooltip(fn($record) => $record->product_name)
                    ->sortable()
                    ->searchable()
                    ->width('35%'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter()
                    ->width('5rem'),

                Tables\Columns\TextColumn::make('packaging')
                    ->label('Emb')
                    ->alignCenter()
                    ->width('5rem'),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Valor UN')
                    ->formatStateUsing(fn($state) => $state !== null ? 'R$ ' . number_format((float) $state, 4, ',', '.') : null)
                    ->alignRight()
                    ->sortable()
                    ->width('9rem'),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->state(fn($record) => $record->quantity * ($record->unit_price ?? 0))
                    ->formatStateUsing(fn($state) => $state !== null ? 'R$ ' . number_format((float) $state, 2, ',', '.') : null)
                    ->alignRight()
                    ->weight('bold')
                    ->width('9rem'),

                Tables\Columns\TextColumn::make('observation')
                    ->label('Obs')
                    ->limit(50)
                    ->tooltip(fn($state) => $state)
                    ->wrap()
                    ->width('20%'),
            ])
            ->filters([
                Filter::make('product_type')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Origem do Produto')
                            ->options([
                                'all' => 'Todos',
                                'registered' => 'Com Cadastro (WinThor)',
                                'manual' => 'Sem Cadastro (Manual)',
                            ])
                            ->default('all'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['type'] === 'registered', fn(Builder $query) => $query->whereNotNull('product_id'))
                            ->when($data['type'] === 'manual', fn(Builder $query) => $query->whereNull('product_id'));
                    }),

                Tables\Filters\TernaryFilter::make('has_expirations')
                    ->label('Status da Validade')
                    ->placeholder('Todos os itens')
                    ->trueLabel('Com validade informada')
                    ->falseLabel('Sem validade (Pendentes)')
                    ->queries(
                        true: fn(Builder $query) => $query->has('expirations'),
                        false: fn(Builder $query) => $query->doesntHave('expirations'),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_line')
                    ->label('')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->hidden(fn() => $this->isLocked()) // <--- ESCONDE EDIÇÃO
                    ->action(fn(RequestItem $record) => $this->editItem($record->id)),

                Tables\Actions\Action::make('manage_expirations')
                    ->label('')
                    ->icon(function (RequestItem $record) {
                        if ($record->expirations->isEmpty()) return 'heroicon-o-calendar-days';
                        $sum = round($record->expirations->sum('quantity'), 4);
                        return $sum < round($record->quantity, 4) ? 'heroicon-s-exclamation-triangle' : 'heroicon-s-calendar-days';
                    })
                    ->color(function (RequestItem $record) {
                        if ($record->expirations->isEmpty()) return 'gray';
                        $sum = round($record->expirations->sum('quantity'), 4);
                        return $sum < round($record->quantity, 4) ? 'warning' : 'success';
                    })
                    ->tooltip(function (RequestItem $record) {
                        if ($record->expirations->isEmpty()) return 'Lançar Validades';
                        $sum = round($record->expirations->sum('quantity'), 4);
                        return $sum < round($record->quantity, 4) ? 'Validade Incompleta (Atenção)' : 'Validades Gravadas';
                    })
                    ->hidden(fn() => $this->isLocked()) // <--- ESCONDE GERENCIAR VALIDADES
                    ->modalHeading(fn(RequestItem $record) => 'Validades: ' . $record->product_name)
                    ->modalDescription(fn(RequestItem $record) => "Quantidade solicitada do item: {$record->quantity}")
                    ->modalWidth('2xl')
                    ->fillForm(fn(RequestItem $record) => [
                        'expirations' => $record->expirations->map(fn($exp) => [
                            'expiration_date' => $exp->expiration_date->format('Y-m-d'),
                            'quantity' => $exp->quantity,
                        ])->toArray(),
                        'short_date_confirmed' => false,
                    ])
                    ->form([
                        Forms\Components\Repeater::make('expirations')
                            ->label('Lotes e Validades')
                            ->addActionLabel('Adicionar Validade')
                            ->schema([
                                Forms\Components\DatePicker::make('expiration_date')
                                    ->label('Data de Validade')
                                    ->required()
                                    ->displayFormat('d/m/Y')
                                    ->live(debounce: 500),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantidade')
                                    ->required()
                                    ->numeric()
                                    ->step('any'),
                            ])
                            ->columns(2)
                            ->live(),

                        Forms\Components\Hidden::make('short_date_confirmed')
                            ->default(false),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('confirm_short_date')
                                ->label('⚠️ Deseja prosseguir com validade menor que 90 dias?')
                                ->color('danger')
                                ->button()
                                ->action(fn(Forms\Set $set) => $set('short_date_confirmed', true))
                        ])->visible(function (Forms\Get $get) {
                            $hasShortDate = false;
                            $limitDate = \Illuminate\Support\Carbon::now()->addDays(90)->startOfDay();

                            foreach ($get('expirations') ?? [] as $exp) {
                                if (!empty($exp['expiration_date']) && \Illuminate\Support\Carbon::parse($exp['expiration_date'])->isBefore($limitDate)) {
                                    $hasShortDate = true;
                                    break;
                                }
                            }
                            return $hasShortDate && !$get('short_date_confirmed');
                        }),

                        Forms\Components\Placeholder::make('aviso_confirmado')
                            ->hiddenLabel()
                            ->content(new \Illuminate\Support\HtmlString('<span class="text-success-600 font-bold dark:text-success-400">✅ Ciência de validade curta confirmada. Você já pode salvar.</span>'))
                            ->visible(fn(Forms\Get $get) => $get('short_date_confirmed')),
                    ])
                    ->action(function (RequestItem $record, array $data, \Filament\Tables\Actions\Action $action) {
                        $totalInformed = round(collect($data['expirations'])->sum('quantity'), 4);
                        $maxQuantity = round($record->quantity, 4);

                        if ($totalInformed > $maxQuantity) {
                            Notification::make()
                                ->danger()
                                ->title('Quantidade Excedida')
                                ->body("Você informou um total de {$totalInformed}, mas o item tem apenas {$maxQuantity} na solicitação. Corrija para salvar.")
                                ->send();

                            $action->halt();
                        }

                        $hasShortDate = collect($data['expirations'])->contains(function ($exp) {
                            return !empty($exp['expiration_date']) &&
                                \Illuminate\Support\Carbon::parse($exp['expiration_date'])->isBefore(\Illuminate\Support\Carbon::now()->addDays(90)->startOfDay());
                        });

                        if ($hasShortDate && empty($data['short_date_confirmed'])) {
                            Notification::make()
                                ->warning()
                                ->title('Ação necessária')
                                ->body('Você inseriu uma validade próxima ao vencimento. Clique no botão vermelho para confirmar antes de salvar.')
                                ->send();

                            $action->halt();
                        }

                        $record->expirations()->delete();
                        if (!empty($data['expirations'])) {
                            foreach ($data['expirations'] as $expData) {
                                $record->expirations()->create([
                                    'expiration_date' => $expData['expiration_date'],
                                    'quantity' => $expData['quantity'],
                                ]);
                            }
                        }

                        Notification::make()->title('Validades salvas com sucesso!')->success()->send();
                    }),


                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->hidden(fn() => $this->isLocked()) // <--- ESCONDE EXCLUSÃO
                    ->before(function (RequestItem $record) {
                        if ($this->editingItemId === $record->id) $this->resetInput();
                    })
                    ->using(function (RequestItem $record) {
                        $record->forceDelete();
                    }),

                Tables\Actions\Action::make('go_to_product')
                    ->label('')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->tooltip('Ver Cadastro Nutricional')
                    ->visible(fn(\App\Models\RequestItem $record) => $record->product_id !== null)
                    // MANTIDO: O usuário ainda pode visualizar o produto original mesmo com a solicitação travada
                    ->url(fn(\App\Models\RequestItem $record): string => \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $record->product_id]))
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
}
