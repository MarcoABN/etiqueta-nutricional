<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RequestResource\Pages;
use App\Models\Request;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
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

    protected static ?string $navigationGroup = 'Gestão';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(12)
                    ->schema([
                        TextInput::make('display_id')
                            ->label('Nº Pedido')
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('#')
                            ->extraInputAttributes(['style' => 'font-weight: bold; font-size: 1.1em;'])
                            ->columnSpan(['default' => 12, 'sm' => 6, 'lg' => 3]),

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
                            ->columnSpan(['default' => 12, 'sm' => 6, 'lg' => 3]),

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
                            ->columnSpan(['default' => 12, 'sm' => 6, 'lg' => 3]),

                        TextInput::make('created_at')
                            ->label('Data Criação')
                            ->disabled()
                            ->formatStateUsing(fn ($record) => $record?->created_at?->format('d/m/Y H:i'))
                            ->columnSpan(['default' => 12, 'sm' => 6, 'lg' => 3]),
                    ])
                    ->hidden(fn (string $operation) => $operation === 'create')
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
                    ->color(fn ($state) => match ($state) {
                        'Aereo' => 'warning',
                        'Maritimo' => 'info',
                        'Avaliar' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'aberto' => 'success',
                        'fechado' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
}