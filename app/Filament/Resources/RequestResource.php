<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RequestResource\Pages;
use App\Models\Request;
use App\Models\Settlement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RequestResource extends Resource
{
    protected static ?string $model = Request::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Solicitações';
    protected static ?string $modelLabel = 'Solicitações';
    protected static ?string $pluralModelLabel = 'Solicitações';

    protected static ?string $navigationGroup = 'Operação';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(12)
                    ->disabled(fn(?Request $record) => ($record?->is_locked ?? false) || ($record?->settlement?->is_locked ?? false))
                    ->schema([
                        TextInput::make('display_id')
                            ->label('Nº Pedido')
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('#')
                            ->extraInputAttributes(['style' => 'font-weight: bold; font-size: 1.1em;'])
                            ->columnSpan(['default' => 12, 'sm' => 6, 'lg' => 2]), // Reduzido de 3 para 2

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'aberto' => 'Em Aberto',
                                'fechado' => 'Finalizado',
                            ])
                            ->default('aberto')
                            ->required()
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->columnSpan(['default' => 12, 'sm' => 6, 'lg' => 2]), // Reduzido de 3 para 2

                        Select::make('shipping_type')
                            ->label('Tipo de Envio')
                            ->options([
                                'Maritimo' => 'Marítimo',
                                'Aereo' => 'Aéreo',
                                'Avaliar' => 'Avaliar'
                            ])
                            ->default('Maritimo')
                            ->required()
                            ->native(false)
                            ->columnSpan(['default' => 12, 'sm' => 6, 'lg' => 2]), // Reduzido de 3 para 2

                        TextInput::make('created_at')
                            ->label('Data Criação')
                            ->disabled()
                            ->formatStateUsing(fn($record) => $record?->created_at?->format('d/m/Y H:i'))
                            ->columnSpan(['default' => 12, 'sm' => 6, 'lg' => 2]), // Reduzido de 3 para 2

                        // NOVO: A observação agora entra na mesma linha dos campos acima (ocupa as 4 colunas restantes)
                        TextInput::make('observation')
                            ->label('Observação da Solicitação')
                            ->placeholder('Observação geral...')
                            ->columnSpan(['default' => 12, 'sm' => 12, 'lg' => 4]),
                    ])
                    ->hidden(fn(string $operation) => $operation === 'create')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Itens')
                    ->counts('items')
                    ->badge(),

                Tables\Columns\TextColumn::make('shipping_type')
                    ->label('Envio')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Aereo' => 'warning',
                        'Maritimo' => 'info',
                        'Avaliar' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'aberto' => 'success',
                        'fechado' => 'gray',
                        default => 'gray',
                    }),

                // NOVO: Exibe a observação na listagem de pedidos
                Tables\Columns\TextColumn::make('observation')
                    ->label('Observação')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(), // Permite ocultar na engrenagem se a tela ficar cheia

                // --- NOVA COLUNA: SINALIZADOR DE CONSOLIDAÇÃO ---
                Tables\Columns\IconColumn::make('is_locked')
                    ->label('Consolidado')
                    ->boolean()
                    ->trueIcon('heroicon-s-lock-closed') // Ícone quando is_locked = true
                    ->falseIcon('heroicon-o-lock-open')  // Ícone quando is_locked = false
                    ->trueColor('success')               // Cadeado verde (Consolidado)
                    ->falseColor('warning')              // Cadeado laranja (Aberto)
                    ->tooltip(fn($state) => $state ? 'Solicitação Consolidada (Somente Leitura)' : 'Solicitação Aberta para Edição')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->hidden(fn(Request $record) => ($record->is_locked) || ($record->settlement?->is_locked ?? false)),
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
            'index' => Pages\ListRequests::route('/'),
            'edit' => Pages\EditRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    // App\Models\Request.php
    public function settlement()
    {
        return $this->hasOne(Settlement::class);
    }
}
