<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShipmentResource\Pages;
use App\Models\Shipment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $modelLabel = 'Remessa';
    protected static ?string $navigationGroup = 'Operação';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Remessa')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Identificação da Remessa')
                            ->placeholder('Ex: Remessa 04')
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição/Observações')
                            ->rows(2),
                    ]),

                Forms\Components\Section::make('Cronograma de Etapas')
                    ->schema([
                        Forms\Components\Repeater::make('steps')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Etapa')
                                    ->placeholder('Ex: Fechamento do pedido')
                                    ->required()
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('scheduled_date')
                                    ->label('Data Prevista')
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->required()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('responsible_name')
                                    ->label('Responsável')
                                    ->columnSpan(1),
                                Forms\Components\Toggle::make('is_completed')
                                    ->label('Concluído?')
                                    ->inline(false)
                                    ->columnSpan(1),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->addActionLabel('Adicionar nova etapa'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Remessa')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('steps_count')
                    ->counts('steps')
                    ->label('Total de Etapas')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->label('Criado em')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageShipments::route('/'),
        ];
    }
}
