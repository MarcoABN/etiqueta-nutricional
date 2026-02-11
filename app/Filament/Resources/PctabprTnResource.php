<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PctabprTnResource\Pages;
use App\Models\PctabprTn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter; // Importante
use Filament\Tables\Filters\TernaryFilter; // Importante
use Illuminate\Database\Eloquent\Builder;

class PctabprTnResource extends Resource
{
    protected static ?string $model = PctabprTn::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Gestão de Preços';
    protected static ?string $modelLabel = 'Produto/Preço';
    protected static ?string $navigationGroup = 'Precificação';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Campos agora editáveis (removemos ->disabled())
                Forms\Components\TextInput::make('CODFILIAL')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('CODPROD')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('DESCRICAO')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('CUSTOULTENT')
                            ->label('Custo Últ. Entrada')
                            ->numeric()
                            ->prefix('R$'),
                            
                        Forms\Components\TextInput::make('PVENDA')
                            ->label('Preço Atual')
                            ->numeric()
                            ->prefix('R$'),
                            
                        Forms\Components\TextInput::make('QTESTOQUE')
                            ->label('Estoque')
                            ->numeric(),

                        Forms\Components\TextInput::make('PVENDA_NOVO')
                            ->label('Novo Preço')
                            ->numeric()
                            ->prefix('R$')
                            // Validação simples no Admin também
                            ->live(onBlur: true)
                            ->helperText(fn ($get, $state) => 
                                ($state > 0 && $state < $get('CUSTOULTENT')) 
                                ? '⚠️ Atenção: Valor abaixo do custo!' 
                                : null
                            )
                            ->columnSpan(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('CODFILIAL')->label('Filial')->sortable(),
                Tables\Columns\TextColumn::make('CODPROD')->label('Cód.')->searchable(),
                Tables\Columns\TextColumn::make('CODAUXILIAR')->label('EAN')->searchable(),
                Tables\Columns\TextColumn::make('DESCRICAO')->label('Descrição')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('CUSTOULTENT')->label('Custo')->money('BRL')->toggleable(),
                
                Tables\Columns\TextColumn::make('PVENDA')->label('Atual')->money('BRL'),
                
                Tables\Columns\TextColumn::make('PVENDA_NOVO')
                    ->label('Novo Preço')
                    ->money('BRL')
                    // Pinta de vermelho se estiver abaixo do custo, verde se preenchido, cinza se vazio
                    ->color(fn (PctabprTn $record) => 
                        ($record->PVENDA_NOVO && $record->PVENDA_NOVO < $record->CUSTOULTENT) ? 'danger' : 
                        ($record->PVENDA_NOVO ? 'success' : 'gray')
                    )
                    ->description(fn (PctabprTn $record) => 
                        ($record->PVENDA_NOVO && $record->PVENDA_NOVO < $record->CUSTOULTENT) ? 'Abaixo do custo!' : ''
                    ),
            ])
            ->filters([
                // Filtro solicitado
                TernaryFilter::make('status_preco')
                    ->label('Status da Precificação')
                    ->placeholder('Todos os produtos')
                    ->trueLabel('Com Novo Preço Definido')
                    ->falseLabel('Pendente (Sem Novo Preço)')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('PVENDA_NOVO')->where('PVENDA_NOVO', '>', 0),
                        false: fn (Builder $query) => $query->whereNull('PVENDA_NOVO')->orWhere('PVENDA_NOVO', 0),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                // (Mantenha sua action de exportação aqui)
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPctabprTns::route('/'),
            'edit' => Pages\EditPctabprTn::route('/{record}/edit'),
        ];
    }
}