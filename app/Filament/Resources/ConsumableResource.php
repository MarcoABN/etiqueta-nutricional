<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConsumableResource\Pages;
use App\Models\Consumable;
use App\Models\ConsumableMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ConsumableResource extends Resource
{
    protected static ?string $model = Consumable::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $modelLabel = 'Recurso Consumível';
    protected static ?string $pluralModelLabel = 'Recursos Consumíveis';
    protected static ?string $navigationGroup = 'Gestão';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Recurso')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Item')
                            ->placeholder('Ex: Pallet PBR, Fita Stretch...')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('unit')
                            ->label('Unidade de Medida')
                            ->placeholder('Ex: un, kg, rolo')
                            ->required()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('min_quantity')
                            ->label('Estoque Mínimo')
                            ->numeric()
                            ->required()
                            ->default(0),

                        // O saldo atual não é editável diretamente para manter a integridade
                        Forms\Components\TextInput::make('current_quantity')
                            ->label('Saldo Atual')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->default(0)
                            ->helperText('O saldo é atualizado automaticamente pelas movimentações.'),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição / Observações')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unid.'),

                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Estoque Atual')
                    ->numeric()
                    ->sortable()
                    ->color(fn(Consumable $record): string => $record->current_quantity <= $record->min_quantity ? 'danger' : 'success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('min_quantity')
                    ->label('Estoque Mín.')
                    ->numeric(),
            ])
            ->actions([
                // AÇÃO DE ENTRADA
                Tables\Actions\Action::make('entrada')
                    ->label('Entrada')
                    ->icon('heroicon-m-arrow-down-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                        Forms\Components\TextInput::make('reason')
                            ->label('Motivo / Origem')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Consumable $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $previousBalance = $record->current_quantity;
                            $currentBalance = $previousBalance + $data['quantity'];

                            ConsumableMovement::create([
                                'consumable_id' => $record->id,
                                'user_id' => auth()->id(),
                                'type' => 'in',
                                'quantity' => $data['quantity'],
                                'previous_balance' => $previousBalance,
                                'current_balance' => $currentBalance,
                                'reason' => $data['reason'],
                            ]);

                            $record->update(['current_quantity' => $currentBalance]);
                        });
                    }),

                // AÇÃO DE SAÍDA
                Tables\Actions\Action::make('saida')
                    ->label('Saída')
                    ->icon('heroicon-m-arrow-up-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                        Forms\Components\TextInput::make('reason')
                            ->label('Motivo / Destino')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Consumable $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $previousBalance = $record->current_quantity;
                            $currentBalance = $previousBalance - $data['quantity'];

                            ConsumableMovement::create([
                                'consumable_id' => $record->id,
                                'user_id' => auth()->id(),
                                'type' => 'out',
                                'quantity' => $data['quantity'],
                                'previous_balance' => $previousBalance,
                                'current_balance' => $currentBalance,
                                'reason' => $data['reason'],
                            ]);

                            $record->update(['current_quantity' => $currentBalance]);
                        });
                    }),

                Tables\Actions\EditAction::make()->iconButton(),
            ])
            ->emptyStateHeading('Nenhum recurso encontrado');
    }

    public static function getRelations(): array
    {
        return [
            ConsumableResource\RelationManagers\MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsumables::route('/'),
            'create' => Pages\CreateConsumable::route('/create'), // Removido o 's'
            'edit' => Pages\EditConsumable::route('/{record}/edit'), // Removido o 's'
        ];
    }
}