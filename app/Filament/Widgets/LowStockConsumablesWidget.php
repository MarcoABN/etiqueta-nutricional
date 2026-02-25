<?php

namespace App\Filament\Widgets;

use App\Models\Consumable;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockConsumablesWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = '⚠️ Alertas de Estoque Baixo';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Consumable::query()
                    ->whereColumn('current_quantity', '<=', 'min_quantity')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Recurso Consumível')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unidade'),

                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Estoque Atual')
                    ->color('danger')
                    ->weight('bold')
                    ->numeric(),

                Tables\Columns\TextColumn::make('min_quantity')
                    ->label('Estoque Mínimo')
                    ->numeric(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Gerenciar')
                    ->url(fn (Consumable $record): string => route('filament.admin.resources.consumables.index'))
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ])
            ->emptyStateHeading('Tudo certo!')
            ->emptyStateDescription('Nenhum recurso está abaixo do estoque mínimo definido.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}