<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DemandResource\Pages;
use App\Filament\Resources\DemandResource\RelationManagers;
use App\Models\Demand;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DemandResource extends Resource
{
    protected static ?string $model = Demand::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $modelLabel = 'Demanda';
    protected static ?string $pluralModelLabel = 'Demandas';
    protected static ?string $navigationGroup = 'Gestão';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        // Linha 1: Título (Ocupa tudo)
                        Forms\Components\TextInput::make('title')
                            ->label('Título da Demanda')
                            ->placeholder('Ex: Compra de material...')
                            ->required()
                            ->columnSpanFull(),

                        // Linha 2: Dados compactos (4 colunas)
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Responsável')
                                    ->relationship('responsible', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'pending' => 'Aguardando',
                                        'started' => 'Iniciado',
                                        'finished' => 'Finalizado',
                                    ])
                                    ->default('pending')
                                    ->required()
                                    ->native(false),

                                Forms\Components\TextInput::make('deadline_days')
                                    ->label('Prazo (dias)')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->formatStateUsing(function (?Demand $record) {
                                        // Se não tiver registro ou não tiver data limite, deixa em branco
                                        if (!$record || !$record->deadline)
                                            return null;

                                        // Calcula a diferença em dias entre a data de criação e a data limite
                                        return (int) round(\Carbon\Carbon::parse($record->created_at)->startOfDay()->diffInDays(\Carbon\Carbon::parse($record->deadline)->startOfDay(), false));
                                    })
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        if ($state !== null && $state !== '') {
                                            $set('deadline', \Carbon\Carbon::now()->addDays((int) $state)->format('Y-m-d'));
                                        }
                                    }),

                                Forms\Components\DatePicker::make('deadline')
                                    ->label('Data Limite')
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set) => $set('deadline_days', null)),
                            ]),

                        // Linha 3: Descrição
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição Detalhada')
                            ->rows(3)
                            ->columnSpanFull(),

                        // Campo Observação removido da tela conforme solicitado
                        // Forms\Components\Textarea::make('observation')->columnSpanFull(),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => auth()->id()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->weight('bold')->limit(40),
                Tables\Columns\TextColumn::make('responsible.name')->label('Responsável')->icon('heroicon-m-user'),
                Tables\Columns\TextColumn::make('deadline')->date('d/m/Y')->label('Prazo')
                    ->color(fn(Demand $record) => ($record->deadline < now() && $record->status !== 'finished') ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'Aguardando',
                        'started' => 'Iniciado',
                        'finished' => 'Finalizado',
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'started' => 'info',
                        'finished' => 'success',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Aguardando', 'started' => 'Iniciado', 'finished' => 'Finalizado']),
                Tables\Filters\SelectFilter::make('user_id')->relationship('responsible', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OccurrencesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDemands::route('/'),
            'create' => Pages\CreateDemand::route('/create'),
            'edit' => Pages\EditDemand::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                // Filtra para mostrar apenas se o usuário logado for o responsável
                $query->where('user_id', auth()->id())
                    // (Recomendado) Permite que ele também veja as demandas que ele mesmo criou
                    ->orWhere('created_by', auth()->id());
            });
    }
}