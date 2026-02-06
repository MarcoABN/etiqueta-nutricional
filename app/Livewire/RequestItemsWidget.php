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

    // Propriedades do Formulário
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
                Forms\Components\Section::make('Adicionar Item')
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
                                    // --- INÍCIO DA MELHORIA DA PESQUISA ---
                                    ->getSearchResultsUsing(function (string $search) {
                                        return Product::query()
                                            ->where(function ($query) use ($search) {
                                                // 1. Busca por NOME (Aceita palavras fora de ordem)
                                                $query->where(function ($subQuery) use ($search) {
                                                    // Quebra a busca em palavras (ex: "top meio" -> ["top", "meio"])
                                                    $terms = array_filter(explode(' ', $search));

                                                    foreach ($terms as $term) {
                                                        // O produto deve conter ESTA palavra (AND)
                                                        // 'ilike' garante que não diferencia maiúscula/minúscula no Postgres
                                                        $subQuery->where('product_name', 'ilike', "%{$term}%");
                                                    }
                                                });

                                                // 2. OU busca por Cód. WinThor (Convertendo para texto para evitar erro no Postgres)
                                                $query->orWhereRaw("CAST(codprod AS TEXT) ILIKE ?", ["%{$search}%"]);

                                                // 3. OU busca por Código de Barras
                                                $query->orWhere('barcode', 'ilike', "%{$search}%");
                                            })
                                            ->limit(50) // Aumentei o limite para facilitar
                                            ->get()
                                            ->mapWithKeys(fn($p) => [$p->id => "{$p->codprod} - {$p->product_name}"]);
                                    })
                                    // --- FIM DA MELHORIA ---
                                    ->getOptionLabelUsing(fn($value): ?string => Product::find($value)?->product_name)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($product = Product::find($state)) {
                                            $set('product_name', $product->product_name);
                                            $set('packaging', $product->serving_size_unit ?? 'UN');
                                            // Garante que o código WinThor seja salvo no item oculto (se existir no seu form)
                                            // $set('winthor_code', $product->codprod); 
                                        }
                                    })
                                    ->columnSpan(4),

                                // 2. NOME DO ITEM
                                Forms\Components\TextInput::make('product_name')
                                    ->label('Descrição do Item')
                                    ->required()
                                    ->columnSpan(3),

                                // 3. DADOS QUANTITATIVOS
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Qtd')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('packaging')
                                    ->label('Emb')
                                    ->options(['CX' => 'CX', 'UN' => 'UN', 'DP' => 'DP', 'PCT' => 'PCT', 'FD' => 'FD'])
                                    ->default('UN')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('shipping_type')
                                    ->label('Envio')
                                    ->options(['Maritimo' => 'Mar', 'Aereo' => 'Aér'])
                                    ->default('Maritimo')
                                    ->required()
                                    ->columnSpan(1),

                                // 4. BOTÃO INCLUIR (Dentro de um container Actions para alinhar)
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('add')
                                        ->label('INCLUIR')
                                        ->icon('heroicon-m-plus')
                                        ->color('primary')
                                        ->action(fn() => $this->addItem())
                                ])
                                    ->columnSpan(2)
                                    ->extraAttributes(['class' => 'mt-6']) // Alinhamento vertical com inputs
                                    ->alignCenter(),
                            ]),

                        Forms\Components\TextInput::make('observation')
                            ->label('Observação (Opcional)')
                            ->placeholder('Detalhes adicionais...')
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public function addItem()
    {
        $data = $this->form->getState();

        // Criação Segura e Imediata
        RequestItem::create([
            'request_id' => $this->requestRecord->id,
            'product_id' => $data['product_id'] ?? null,
            'product_name' => $data['product_name'],
            'quantity' => $data['quantity'],
            'packaging' => $data['packaging'],
            'shipping_type' => $data['shipping_type'],
            'observation' => $data['observation'],
            'winthor_code' => Product::find($data['product_id'] ?? null)?->codprod,
        ]);

        // Reset do Formulário
        $this->form->fill([
            'product_id' => null,
            'product_name' => '',
            'quantity' => 1,
            'packaging' => 'UN',
            'observation' => '',
        ]);

        Notification::make()->title('Item adicionado')->success()->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RequestItem::query()
                    ->where('request_id', $this->requestRecord->id)
                    ->orderBy('created_at', 'desc')
                    ->withTrashed() // Exibe itens excluídos para permitir restauração
            )
            ->heading('Itens Gravados')
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produto')
                    ->description(fn($record) => $record->winthor_code ? "Cód: {$record->winthor_code}" : "Manual")
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('packaging')
                    ->label('Emb'),

                Tables\Columns\TextColumn::make('shipping_type')
                    ->label('Envio')
                    ->badge()
                    ->color(fn($state) => $state === 'Aereo' ? 'warning' : 'info'),

                Tables\Columns\TextColumn::make('observation')
                    ->label('Obs')
                    ->limit(30),

                // Badge de Excluído
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Status')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn($state) => $state ? 'LIXEIRA' : null)
                    ->getStateUsing(fn($record) => $record->deleted_at),
            ])
            ->actions([
                // Excluir (Soft Delete)
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Excluir item'),

                // Restaurar (Aparece apenas se excluído)
                Tables\Actions\RestoreAction::make()
                    ->label('Desfazer')
                    ->button()
                    ->size('xs'),
            ])
            ->paginated(false); // Lista fluida sem paginação
    }

    public function render()
    {
        return view('livewire.request-items-widget');
    }
}
