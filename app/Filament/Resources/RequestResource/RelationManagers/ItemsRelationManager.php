<?php

namespace App\Filament\Resources\RequestResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Itens da Solicitação';
    protected static ?string $modelLabel = 'Item';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        // CAMPO DE BUSCA INTELIGENTE
                        Forms\Components\Select::make('product_search_helper')
                            ->label('Buscar Produto (Opcional)')
                            ->helperText('Busque por Nome, Código WinThor ou Barras. Deixe vazio para cadastrar item manual.')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                return Product::query()
                                    ->where('product_name', 'ilike', "%{$search}%")
                                    ->orWhere('codprod', 'ilike', "%{$search}%") // Postgres cast auto ou search string
                                    ->orWhere('barcode', 'ilike', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($product) {
                                        // Formata o resultado da busca
                                        $label = "[{$product->codprod}] {$product->product_name}";
                                        return [$product->id => $label];
                                    });
                            })
                            ->live() // Reage imediatamente à seleção
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $product = Product::find($state);
                                    if ($product) {
                                        $set('product_id', $product->id);
                                        $set('product_name', $product->product_name);
                                        $set('winthor_code', $product->codprod); // Garante que é int ou null
                                    }
                                }
                            })
                            ->columnSpanFull(),

                        // CAMPOS REAIS DO BANCO
                        // Oculto, armazena o ID se selecionado
                        Forms\Components\Hidden::make('product_id'),
                        
                        Forms\Components\Hidden::make('winthor_code'),

                        Forms\Components\TextInput::make('product_name')
                            ->label('Nome do Produto')
                            ->required()
                            ->placeholder('Selecione acima ou digite o nome')
                            ->columnSpanFull(),
                        
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantidade')
                                    ->numeric()
                                    ->required(),

                                Forms\Components\Select::make('packaging')
                                    ->label('Embalagem')
                                    ->options([
                                        'CX' => 'Caixa (CX)',
                                        'UN' => 'Unidade (UN)',
                                        'DP' => 'Display (DP)',
                                        'PCT' => 'Pacote (PCT)',
                                        'FD' => 'Fardo (FD)',
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('shipping_type')
                                    ->label('Envio')
                                    ->options([
                                        'Maritimo' => 'Marítimo',
                                        'Aereo' => 'Aéreo',
                                    ])
                                    ->default('Maritimo')
                                    ->required(),
                            ]),
                        
                        Forms\Components\Textarea::make('observation')
                            ->label('Observação')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // Ordenação visual padrão
            ->defaultSort(function (Builder $query) {
                // Ordena: Primeiro quem tem ID (cadastrados), depois quem não tem (manuais)
                // No Postgres: NULLS LAST ou logica custom
                return $query->orderByRaw('product_id IS NULL') 
                             ->orderBy('created_at', 'desc');
            })
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produto')
                    ->description(fn ($record) => $record->product_id ? "WinThor: {$record->winthor_code}" : "Item Manual")
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd'),

                Tables\Columns\TextColumn::make('packaging')
                    ->label('Emb.'),

                Tables\Columns\TextColumn::make('shipping_type')
                    ->label('Envio')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aereo' => 'warning',
                        'Maritimo' => 'info',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar Item')
                    ->modalHeading('Adicionar Produto à Solicitação')
                    ->slideOver() // Abre num painel lateral chique
                    ->createAnother(true), // Permite criar um e já abrir o proximo (fluxo rápido)
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}