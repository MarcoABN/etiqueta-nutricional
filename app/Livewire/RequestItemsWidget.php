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
use Filament\Notifications\Notification;
use Livewire\Component;

class RequestItemsWidget extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public Request $requestRecord;

    // Controle de Edição
    public ?string $editingItemId = null; // Armazena o ID se estivermos editando

    // Dados do formulário
    public $product_id;
    public $product_name;
    public $quantity = 1;
    public $packaging = 'UN';
    public $shipping_type = 'Maritimo';
    public $observation;

    public function mount(Request $record)
    {
        $this->requestRecord = $record;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(fn () => $this->editingItemId ? 'Editar Item' : 'Adicionar Item')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(12)
                            ->schema([
                                // 1. BUSCA INTELIGENTE
                                Forms\Components\Select::make('product_id')
                                    ->label('Buscar Produto')
                                    ->placeholder('Digite Nome, Cód ou EAN')
                                    ->searchable()
                                    ->live()
                                    ->getSearchResultsUsing(function (string $search) {
                                        return Product::query()
                                            ->where(function ($query) use ($search) {
                                                $query->where(function ($subQuery) use ($search) {
                                                    $terms = array_filter(explode(' ', $search));
                                                    foreach ($terms as $term) {
                                                        $subQuery->where('product_name', 'ilike', "%{$term}%");
                                                    }
                                                });
                                                $query->orWhereRaw("CAST(codprod AS TEXT) ILIKE ?", ["%{$search}%"]);
                                                $query->orWhere('barcode', 'ilike', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($p) => [$p->id => "{$p->codprod} - {$p->product_name}"]);
                                    })
                                    ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->product_name)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($product = Product::find($state)) {
                                            $set('product_name', $product->product_name);
                                            $set('packaging', $product->serving_size_unit ?? 'UN');
                                        }
                                    })
                                    ->columnSpan(4),

                                // 2. DADOS DO ITEM
                                Forms\Components\TextInput::make('product_name')
                                    ->label('Descrição do Item')
                                    ->required()
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Qtd')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('packaging')
                                    ->label('Emb')
                                    ->options(['CX'=>'CX', 'UN'=>'UN', 'DP'=>'DP', 'PCT'=>'PCT', 'FD'=>'FD'])
                                    ->default('UN')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('shipping_type')
                                    ->label('Envio')
                                    ->options(['Maritimo'=>'Mar', 'Aereo'=>'Aér'])
                                    ->default('Maritimo')
                                    ->required()
                                    ->columnSpan(1),

                                // 3. BOTÕES DE AÇÃO
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
                                ->columnSpan(2)
                                ->extraAttributes(['class' => 'mt-6 gap-2'])
                                ->alignCenter(),
                            ]),
                            
                        Forms\Components\TextInput::make('observation')
                            ->label('Observação')
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public function saveItem()
    {
        $data = $this->form->getState();
        $prodId = $data['product_id'] ?? null;

        // VALIDAÇÃO DE DUPLICIDADE
        if ($prodId) {
            $exists = RequestItem::where('request_id', $this->requestRecord->id)
                ->where('product_id', $prodId)
                ->when($this->editingItemId, fn ($q) => $q->where('id', '!=', $this->editingItemId))
                ->exists();

            if ($exists) {
                Notification::make()
                    ->title('Item Duplicado')
                    ->body('Este produto já foi adicionado a este pedido.')
                    ->warning()
                    ->send();
                return;
            }
        }

        if ($this->editingItemId) {
            $item = RequestItem::find($this->editingItemId);
            $item->update([
                'product_id' => $data['product_id'],
                'product_name' => $data['product_name'],
                'quantity' => $data['quantity'],
                'packaging' => $data['packaging'],
                'shipping_type' => $data['shipping_type'],
                'observation' => $data['observation'],
                'winthor_code' => Product::find($prodId)?->codprod,
            ]);
            
            Notification::make()->title('Item atualizado com sucesso')->success()->send();
        } else {
            RequestItem::create([
                'request_id' => $this->requestRecord->id,
                'product_id' => $prodId,
                'product_name' => $data['product_name'],
                'quantity' => $data['quantity'],
                'packaging' => $data['packaging'],
                'shipping_type' => $data['shipping_type'],
                'observation' => $data['observation'],
                'winthor_code' => Product::find($prodId)?->codprod,
            ]);

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
            'shipping_type' => $item->shipping_type,
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
            'packaging' => 'UN',
            'shipping_type' => 'Maritimo',
            'observation' => '',
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RequestItem::query()
                    ->where('request_id', $this->requestRecord->id)
                    ->orderBy('created_at', 'desc')
            )
            ->heading('Itens Gravados')
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produto')
                    ->description(fn ($record) => $record->winthor_code ? "Cód: {$record->winthor_code}" : "Manual")
                    ->weight('bold')
                    ->wrap(),
                
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('packaging')->label('Emb'),
                
                Tables\Columns\TextColumn::make('shipping_type')
                    ->label('Envio')
                    ->badge()
                    ->color(fn ($state) => $state === 'Aereo' ? 'warning' : 'info'),

                Tables\Columns\TextColumn::make('observation')->label('Obs')->limit(20),
            ])
            ->actions([
                // 1. BOTÃO EDITAR
                Tables\Actions\Action::make('edit_line')
                    ->label('')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->tooltip('Editar este item')
                    ->action(fn (RequestItem $record) => $this->editItem($record->id)),

                // 2. BOTÃO EXCLUIR ORIGINAL (CORRIGIDO)
                Tables\Actions\DeleteAction::make() // Voltamos para o DeleteAction nativo
                    ->label('')
                    ->tooltip('Excluir item permanentemente')
                    ->before(function (RequestItem $record) {
                        // Limpa o form se estiver editando o item que vai ser apagado
                        if ($this->editingItemId === $record->id) {
                            $this->resetInput();
                        }
                    })
                    // Usamos 'using' para customizar o que acontece quando o usuário confirma
                    ->using(function (RequestItem $record) {
                        $record->forceDelete(); // Força a exclusão física
                    }),
            ])
            ->paginated(false);
    }

    public function render()
    {
        return view('livewire.request-items-widget');
    }
}