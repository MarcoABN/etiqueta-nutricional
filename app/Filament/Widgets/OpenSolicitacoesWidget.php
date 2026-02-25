<?php

namespace App\Filament\Widgets;

use App\Models\Demand; // <-- Apontando para o Model correto que criamos
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OpenSolicitacoesWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    
    // Título que vai aparecer no painel
    protected static ?string $heading = '📋 Demandas em Aberto';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Filtra apenas as demandas que estão Aguardando ou Iniciadas
                Demand::query()->whereIn('status', ['pending', 'started'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Demanda')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('responsible.name')
                    ->label('Responsável')
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Aguardando',
                        'started' => 'Iniciado',
                        'finished' => 'Finalizado',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'started' => 'info',
                        'finished' => 'success',
                    }),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->sortable()
                    // Fica vermelho se estiver atrasado
                    ->color(fn (Demand $record) => ($record->deadline < now()) ? 'danger' : 'gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Acessar')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    // Rota corrigida para o DemandResource
                    ->url(fn (Demand $record): string => route('filament.admin.resources.demands.edit', $record)),
            ])
            ->emptyStateHeading('Nenhuma demanda pendente')
            ->emptyStateDescription('Tudo limpo por aqui!')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}