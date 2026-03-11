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
                                    ->step('0.0001')
                                    ->default(1)
                                    ->required()
                                    ->readOnly(fn (Forms\Get $get) => $get('is_weight_mode'))
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
                                    ->readOnly(fn (Forms\Get $get) => $get('is_weight_mode'))
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
                                            ->content(fn (Forms\Get $get) => new HtmlString(
                                                "<div class='text-xs text-gray-500 mt-6'>" . 
                                                (!$get('pesoliq') || !$get('qtunitcx') ? 
                                                    "<span class='text-danger-600 dark:text-danger-400'>⚠️ Falta Peso Líquido ou Qtd/CX. Ajuste no cadastro e clique no botão de recarregar.</span>" : 
                                                    "ℹ️ Digite o Peso e o Valor da NF. O sistema calculará usando Peso Líq ({$get('pesoliq')}Kg) e Qtd/CX ({$get('qtunitcx')}).") . 
                                                "</div>"
                                            ))
                                            ->columnSpan(['default' => 12, 'lg' => 5]), 
                                    ])
                                    ->visible(fn (Forms\Get $get) => $get('is_weight_mode'))
                                    ->columnSpanFull(),
                            ]),
                    ])
            ]);
    }

    public function saveItem()
    {
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
            
            // ==========================================
            // NOVO: HEADER ACTIONS (FICA NO CANTO DIREITO)
            // ==========================================
            ->headerActions([
                Tables\Actions\Action::make('totals_display')
                    ->label(function () {
                        $items = RequestItem::where('request_id', $this->record->id)->get();
                        
                        $totalVolumes = $items->sum('quantity');
                        $totalValue = $items->sum(fn ($item) => $item->quantity * ($item->unit_price ?? 0));

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
                        // Desativa o hover e cursor de clique para agir puramente como um display visual
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
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produto')
                    ->weight('bold')
                    ->limit(45)
                    ->tooltip(fn($record) => $record->product_name)
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('packaging')
                    ->label('Emb'),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Valor UN')
                    ->formatStateUsing(fn ($state) => $state !== null ? 'R$ ' . number_format((float) $state, 4, ',', '.') : null)
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->state(fn ($record) => $record->quantity * ($record->unit_price ?? 0))
                    ->formatStateUsing(fn ($state) => $state !== null ? 'R$ ' . number_format((float) $state, 2, ',', '.') : null)
                    ->alignRight()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('observation')
                    ->label('Obs')
                    ->limit(20)
                    ->tooltip(fn($state) => $state),
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
            ])
            ->actions([
                Tables\Actions\Action::make('edit_line')
                    ->label('')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->action(fn(RequestItem $record) => $this->editItem($record->id)),

                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->before(function (RequestItem $record) {
                        if ($this->editingItemId === $record->id) $this->resetInput();
                    })
                    ->using(function (RequestItem $record) {
                        $record->forceDelete();
                    }),
            ])
            ->paginated(false);
    }
}