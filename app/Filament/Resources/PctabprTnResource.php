<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PctabprTnResource\Pages;
use App\Models\PctabprTn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action; // Importante para o botão customizado
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse; // Importante para o download

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
                Forms\Components\TextInput::make('CODFILIAL')
                    ->label('Filial')
                    ->disabled(),
                Forms\Components\TextInput::make('CODPROD')
                    ->label('Cód. Produto')
                    ->disabled(),
                Forms\Components\TextInput::make('DESCRICAO')
                    ->label('Descrição')
                    ->disabled()
                    ->columnSpan(2),
                
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('CUSTOULTENT')
                            ->label('Custo Últ. Entrada')
                            ->numeric()
                            ->prefix('R$')
                            ->disabled(),
                            
                        Forms\Components\TextInput::make('PVENDA')
                            ->label('Preço Atual')
                            ->numeric()
                            ->prefix('R$')
                            ->disabled(),
                            
                        Forms\Components\TextInput::make('QTESTOQUE')
                            ->label('Estoque')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('PVENDA_NOVO')
                            ->label('Novo Preço')
                            ->numeric()
                            ->prefix('R$')
                            ->live(onBlur: true)
                            ->helperText(fn ($get, $state) => 
                                ($state > 0 && $state < $get('CUSTOULTENT')) 
                                ? '⚠️ Atenção: Valor abaixo do custo!' 
                                : null
                            ),
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
                
                Tables\Columns\TextColumn::make('CUSTOULTENT')
                    ->label('Custo')
                    ->money('BRL')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('PVENDA')
                    ->label('Atual')
                    ->money('BRL'),
                
                Tables\Columns\TextColumn::make('PVENDA_NOVO')
                    ->label('Novo Preço')
                    ->money('BRL')
                    ->sortable()
                    ->color(fn (PctabprTn $record) => 
                        ($record->PVENDA_NOVO && $record->PVENDA_NOVO < $record->CUSTOULTENT) ? 'danger' : 
                        ($record->PVENDA_NOVO ? 'success' : 'gray')
                    )
                    ->description(fn (PctabprTn $record) => 
                        ($record->PVENDA_NOVO && $record->PVENDA_NOVO < $record->CUSTOULTENT) ? 'Abaixo do custo!' : ''
                    ),
            ])
            ->filters([
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
                // AQUI ESTÁ O BOTÃO QUE FALTAVA NA LISTAGEM
                Action::make('export_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () {
                        return static::exportToCsv();
                    })
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPctabprTns::route('/'),
            'edit' => Pages\EditPctabprTn::route('/{record}/edit'),
        ];
    }

    // Função de exportação otimizada
    public static function exportToCsv()
    {
        $response = new StreamedResponse(function () {
            // Abre o output stream
            $handle = fopen('php://output', 'w');

            // Adiciona BOM para Excel reconhecer acentos UTF-8
            fputs($handle, "\xEF\xBB\xBF");

            // Cabeçalho do CSV
            fputcsv($handle, ['FILIAL', 'CODPROD', 'EAN', 'DESCRICAO', 'CUSTO', 'PRECO_ATUAL', 'ESTOQUE', 'NOVO_PRECO'], ';');

            // Processa em blocos para não estourar memória
            PctabprTn::chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->CODFILIAL,
                        $row->CODPROD,
                        $row->CODAUXILIAR,
                        $row->DESCRICAO,
                        number_format($row->CUSTOULTENT, 2, ',', '.'),
                        number_format($row->PVENDA, 2, ',', '.'),
                        number_format($row->QTESTOQUE, 2, ',', '.'),
                        $row->PVENDA_NOVO ? number_format($row->PVENDA_NOVO, 2, ',', '.') : '',
                    ], ';');
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="precos_export.csv"');

        return $response;
    }
}