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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(fn () => $this->editingItemId ? 'Editar Item' : 'Adicionar Novo Item')
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
                                            ->mapWithKeys(fn ($p) => [$p->id => "{$p->codprod} - {$p->product_name}"]);
                                    })
                                    ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->product_name)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($product = Product::find($state)) {
                                            $set('product_name', $product->product_name);
                                            $set('packaging', $product->serving_size_unit ?? 'CX');
                                        }
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 4, 'lg' => 3]),

                                Forms\Components\TextInput::make('product_name')
                                    ->label('Descrição do Item')
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 8, 'lg' => 5]),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Qtd')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(['default' => 6, 'md' => 2, 'lg' => 1]),

                                Forms\Components\Select::make('packaging')
                                    ->label('Emb')
                                    ->options(['CX'=>'CX', 'UN'=>'UN', 'DP'=>'DP', 'PCT'=>'PCT', 'FD'=>'FD'])
                                    ->default('CX')
                                    ->required()
                                    ->columnSpan(['default' => 6, 'md' => 2, 'lg' => 1]),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Valor UN(R$)')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->columnSpan(['default' => 12, 'md' => 3, 'lg' => 2]),

                                Forms\Components\TextInput::make('observation')
                                    ->label('Observação')
                                    ->columnSpan(['default' => 12, 'md' => 6, 'lg' => 9]),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('save')
                                        ->label(fn () => $this->editingItemId ? 'ATUALIZAR' : 'INCLUIR')
                                        ->icon(fn () => $this->editingItemId ? 'heroicon-m-check' : 'heroicon-m-plus')
                                        ->color(fn () => $this->editingItemId ? 'warning' : 'primary')
                                        ->action(fn () => $this->saveItem()),

                                    Forms\Components\Actions\Action::make('cancel')
                                        ->label('CANCELAR')
                                        ->icon('heroicon-m-x-mark')
                                        ->color('gray')
                                        ->action(fn () => $this->resetInput())
                                        ->visible(fn () => $this->editingItemId !== null),
                                ])
                                ->columnSpan(['default' => 12, 'md' => 3, 'lg' => 3])
                                ->extraAttributes(['class' => 'mt-8 flex justify-end gap-2']) 
                                ->alignRight(),
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
                ->when($this->editingItemId, fn ($q) => $q->where('id', '!=', $this->editingItemId))
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
        
        $this->form->fill([
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'quantity' => $item->quantity,
            'packaging' => $item->packaging,
            'unit_price' => $item->unit_price,
            'observation' => $item->observation,
        ]);
    }

    public function resetInput()
    {
        $this->editingItemId = null;
        $this->form->fill([
            'product_id' => null,
            'product_name' => '',
            'quantity' => 1,
            'packaging' => 'CX',
            'unit_price' => null,
            'observation' => '',
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
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produto')
                    ->description(fn ($record) => $record->winthor_code ? "Cód: {$record->winthor_code}" : "Manual")
                    ->weight('bold')
                    ->wrap()
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('packaging')
                    ->label('Emb'),
                
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Valor UN')
                    ->money('BRL')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('observation')
                    ->label('Obs')
                    ->limit(20)
                    ->tooltip(fn ($state) => $state),
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
                            ->when($data['type'] === 'registered', fn (Builder $query) => $query->whereNotNull('product_id'))
                            ->when($data['type'] === 'manual', fn (Builder $query) => $query->whereNull('product_id'));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_line')
                    ->label('')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->action(fn (RequestItem $record) => $this->editItem($record->id)),

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