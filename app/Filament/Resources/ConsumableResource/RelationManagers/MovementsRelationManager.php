<?php

namespace App\Filament\Resources\ConsumableResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Histórico de Movimentações';

    protected static ?string $icon = 'heroicon-o-clipboard-document-list';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('reason')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->icon('heroicon-m-user')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in' => 'Entrada',
                        'out' => 'Saída',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd.')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('previous_balance')
                    ->label('Saldo Ant.')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true), // Oculto por padrão para limpar a tela

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Saldo Novo')
                    ->weight('bold')
                    ->color('info'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(50)
                    ->tooltip(fn ($state) => $state),
            ])
            ->filters([
                // Filtro para ver só entradas ou só saídas
                Tables\Filters\SelectFilter::make('type')
                    ->label('Filtrar por Tipo')
                    ->options([
                        'in' => 'Entrada',
                        'out' => 'Saída',
                    ]),
            ])
            ->headerActions([
                // Não colocamos CreateAction aqui para forçar o uso dos botões da tabela principal
            ])
            ->actions([
                // Sem Edit/Delete para manter auditoria (segurança)
            ])
            ->bulkActions([
                // Sem ações em massa
            ])
            ->defaultSort('created_at', 'desc'); // Mais recentes primeiro
    }
}