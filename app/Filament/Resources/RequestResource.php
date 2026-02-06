<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RequestResource\Pages;
use App\Livewire\RequestItemsWidget;
use App\Models\Request;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Livewire;
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // --- SEÇÃO 1: CABEÇALHO COMPACTO ---
                Section::make()
                    ->compact()
                    ->columns(12) 
                    ->schema([
                        TextInput::make('display_id')
                            ->label('Nº Pedido')
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('#')
                            ->extraInputAttributes(['style' => 'font-weight: bold; color: #333;'])
                            ->columnSpan(2),

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
                            ->columnSpan(2),

                        TextInput::make('created_at')
                            ->label('Data')
                            ->disabled()
                            ->formatStateUsing(fn ($record) => $record?->created_at?->format('d/m/Y H:i'))
                            ->columnSpan(2),
                            
                        // Resumo visual
                        Placeholder::make('total_items')
                            ->label('Resumo')
                            ->content(fn ($record) => $record ? "Total de Itens: " . $record->items()->count() : '')
                            ->extraAttributes(['class' => 'text-right font-bold text-primary-600'])
                            ->columnSpan(6),
                    ]),

                // --- SEÇÃO 2: WIDGET DE ITENS (Fluxo POS) ---
                Section::make('Itens do Pedido')
                    ->compact()
                    ->schema([
                        // Injeta o componente Livewire customizado
                        Livewire::make(RequestItemsWidget::class)
                            ->key('items-widget')
                            ->data(fn (?Request $record) => ['record' => $record]),
                    ])
                    // Oculta na criação (embora não usemos a tela de criação padrão)
                    ->hidden(fn (string $operation) => $operation === 'create'),
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
                // Filtro para ver itens excluídos (Soft Delete)
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                // Ações de Exclusão e Restauração
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRequests::route('/'),
            // Removemos a rota 'create' para forçar o uso da action rápida na listagem
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