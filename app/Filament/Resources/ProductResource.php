<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Services\GeminiFdaTranslator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Produtos';

    protected static ?string $modelLabel = 'Produto';
    protected static ?string $pluralModelLabel = 'Produtos';

    public static function form(Form $form): Form
    {
        return $form
            ->extraAttributes([
                'x-on:keydown.enter.prevent' => <<<'JS'
                    let inputs = [...$el.closest('form').querySelectorAll('input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled])')];
                    let index = inputs.indexOf($event.target);
                    if (index > -1 && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                        inputs[index + 1].select();
                    }
                JS,
            ])
            ->schema([
                Section::make('Identificação e Status')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(12)
                            ->schema([
                                TextInput::make('codprod')
                                    ->label('Cód. WinThor')
                                    ->required()
                                    ->numeric()
                                    ->unique(ignoreRecord: true)
                                    ->columnSpan(2),
                                TextInput::make('barcode')
                                    ->label('Cód. Barras')
                                    ->unique(ignoreRecord: true)
                                    ->columnSpan(3),

                                Select::make('curve')
                                    ->label('Curva')
                                    ->options([
                                        'A' => 'Curva A',
                                        'B' => 'Curva B',
                                        'C' => 'Curva C',
                                    ])
                                    ->columnSpan(2),

                                Select::make('import_status')
                                    ->label('Status Importação')
                                    ->options([
                                        'Bloqueado' => 'Bloqueado',
                                        'Processado (IA)' => 'Processado (IA)',
                                        'Em Análise' => 'Em Análise',
                                        'Liberado' => 'Liberado',
                                    ])
                                    ->default('Bloqueado')
                                    ->required()
                                    ->columnSpan(5)
                                    ->selectablePlaceholder(false),

                                TextInput::make('product_name')
                                    ->label('Nome do Produto')
                                    ->required()
                                    ->columnSpan(12),
                                TextInput::make('product_name_en')
                                    ->label('Nome (Inglês - Tradução)')
                                    ->columnSpan(12),
                            ]),

                        Forms\Components\Grid::make(6)
                            ->schema([
                                TextInput::make('servings_per_container')->label('Porções/Emb.')->numeric()->columnSpan(1),
                                TextInput::make('serving_weight')->label('Peso Porção')->columnSpan(1),
                                TextInput::make('serving_size_quantity')->label('Qtd Medida')->columnSpan(2),
                                TextInput::make('serving_size_unit')->label('Unidade')->columnSpan(2),
                            ]),
                    ]),

                Section::make('Tabela Nutricional (Macronutrientes)')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Group::make()->schema([
                                    TextInput::make('calories')
                                        ->label('Valor energético (kcal)')
                                        ->extraInputAttributes(['class' => 'font-bold text-lg']),

                                    Forms\Components\Grid::make(4)->schema([
                                        TextInput::make('total_carb')->label('Carbo (g)')->columnSpan(3),
                                        TextInput::make('total_carb_dv')->label('%VD')->columnSpan(1),

                                        TextInput::make('total_sugars')->label('Açúcares Tot (g)')->columnSpan(4),
                                        TextInput::make('added_sugars')->label('Açúcares Add (g)')->columnSpan(3),
                                        TextInput::make('added_sugars_dv')->label('%VD')->columnSpan(1),

                                        TextInput::make('protein')->label('Proteínas (g)')->columnSpan(3),
                                        TextInput::make('protein_dv')->label('%VD')->columnSpan(1),
                                    ]),
                                ]),

                                Group::make()->schema([
                                    Forms\Components\Grid::make(4)->schema([
                                        TextInput::make('total_fat')->label('Gorduras Totais (g)')->columnSpan(3),
                                        TextInput::make('total_fat_dv')->label('%VD')->columnSpan(1),

                                        TextInput::make('sat_fat')->label('Gord. Sat (g)')->columnSpan(3),
                                        TextInput::make('sat_fat_dv')->label('%VD')->columnSpan(1),

                                        TextInput::make('trans_fat')->label('Gord. Trans (g)')->columnSpan(3),
                                        TextInput::make('trans_fat_dv')->label('%VD')->columnSpan(1),

                                        TextInput::make('fiber')->label('Fibras (g)')->columnSpan(3),
                                        TextInput::make('fiber_dv')->label('%VD')->columnSpan(1),

                                        TextInput::make('sodium')->label('Sódio (mg)')->columnSpan(3),
                                        TextInput::make('sodium_dv')->label('%VD')->columnSpan(1),

                                        TextInput::make('cholesterol')->label('Colesterol (mg)')->columnSpan(3),
                                        TextInput::make('cholesterol_dv')->label('%VD')->columnSpan(1),
                                    ]),
                                ]),
                            ]),
                    ]),

                Section::make('Rotulagem e Ingredientes')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Textarea::make('ingredients')->label('Lista de Ingredientes (Inglês)')->rows(4)->columnSpan(2),
                            Group::make()->schema([
                                TextInput::make('allergens_contains')->label('CONTÉM (Alérgicos)'),
                                TextInput::make('allergens_may_contain')->label('PODE CONTER'),
                            ])->columnSpan(1),
                        ]),
                    ]),

                Section::make('Micronutrientes (Vitaminas e Minerais)')
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(4)->schema([
                            TextInput::make('vitamin_d')->label('Vit D'),
                            TextInput::make('calcium')->label('Cálcio'),
                            TextInput::make('iron')->label('Ferro'),
                            TextInput::make('potassium')->label('Potássio'),

                            TextInput::make('vitamin_a')->label('Vit A'),
                            TextInput::make('vitamin_c')->label('Vit C'),
                            TextInput::make('vitamin_e')->label('Vit E'),
                            TextInput::make('vitamin_k')->label('Vit K'),

                            TextInput::make('thiamin')->label('Tiamina (B1)'),
                            TextInput::make('riboflavin')->label('Riboflavina (B2)'),
                            TextInput::make('niacin')->label('Niacina (B3)'),
                            TextInput::make('vitamin_b6')->label('Vit B6'),

                            TextInput::make('folate')->label('Folato'),
                            TextInput::make('vitamin_b12')->label('Vit B12'),
                            TextInput::make('biotin')->label('Biotina'),
                            TextInput::make('pantothenic_acid')->label('Ác. Pantotênico'),

                            TextInput::make('phosphorus')->label('Fósforo'),
                            TextInput::make('iodine')->label('Iodo'),
                            TextInput::make('magnesium')->label('Magnésio'),
                            TextInput::make('zinc')->label('Zinco'),

                            TextInput::make('selenium')->label('Selênio'),
                            TextInput::make('copper')->label('Cobre'),
                            TextInput::make('manganese')->label('Manganês'),
                            TextInput::make('chromium')->label('Cromo'),

                            TextInput::make('molybdenum')->label('Molibdênio'),
                            TextInput::make('chloride')->label('Cloreto'),
                        ]),
                    ]),

                Section::make('Imagem do Rótulo')
                    ->description('Visualize a imagem capturada pelo scanner ou anexe manualmente.')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        FileUpload::make('image_nutritional')
                            ->label('Foto Tabela Nutricional')
                            ->image()
                            ->imageEditor()
                            ->directory('uploads/nutritional')
                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, $get) {
                                $cod = $get('codprod') ?? 'SEM_COD';
                                return "{$cod}_nutri_" . time() . '.' . $file->getClientOriginalExtension();
                            })
                            ->visibility('public')
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                Tables\Columns\TextColumn::make('codprod')
                    ->label('Cód. WinThor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('barcode')
                    ->label('EAN')
                    ->searchable()
                    ->color('gray')
                    ->copyable(),

                Tables\Columns\TextColumn::make('curve')
                    ->label('Curva')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'A' => 'success',
                        'B' => 'warning',
                        'C' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => $state ?? '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('import_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Liberado' => 'success',
                        'Processado (IA)' => 'info', // CORREÇÃO: Cor adicionada para visualização correta
                        'Em Análise' => 'warning',
                        'Bloqueado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => $state ?? 'Indefinido')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produto')
                    ->searchable()
                    ->description(fn(Product $record) => $record->product_name_en)
                    ->limit(40),
            ])
            ->defaultSort('created_at', 'desc')

            ->filters([
                SelectFilter::make('curve')
                    ->label('Filtrar por Curva')
                    ->options([
                        'A' => 'Curva A',
                        'B' => 'Curva B',
                        'C' => 'Curva C',
                    ]),

                SelectFilter::make('import_status')
                    ->label('Status de Importação')
                    ->options([
                        'Liberado' => 'Liberado',
                        'Processado (IA)' => 'Processado (IA)', // CORREÇÃO: Opção adicionada ao filtro
                        'Em Análise' => 'Em Análise',
                        'Bloqueado' => 'Bloqueado',
                    ]),

                Filter::make('cadastro_status')
                    ->form([
                        Select::make('status')
                            ->label('Status do Cadastro')
                            ->options([
                                'finalizado' => 'Cadastro Finalizado (Pronto)',
                                'pendente' => 'Cadastro Pendente (Incompleto)',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['status'],
                            function (Builder $query, $status): Builder {
                                if ($status === 'finalizado') {
                                    return $query->whereNotNull('product_name_en')
                                        ->where('product_name_en', '!=', '')
                                        ->whereNotNull('ingredients')
                                        ->where('ingredients', '!=', '')
                                        ->whereNotNull('calories')
                                        ->whereNotNull('allergens_contains');
                                }

                                if ($status === 'pendente') {
                                    return $query->where(function (Builder $query) {
                                        $query->whereNull('product_name_en')
                                            ->orWhere('product_name_en', '')
                                            ->orWhereNull('ingredients')
                                            ->orWhere('ingredients', '')
                                            ->orWhereNull('calories');
                                    });
                                }

                                return $query;
                            }
                        );
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('export_csv')
                        ->label('Exportar CSV Selecionados')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            return response()->streamDownload(function () use ($records) {
                                echo "\xEF\xBB\xBF";
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['Cod. WinThor', 'Produto', 'Traducao', 'EAN', 'Curva', 'Status Importacao'], ';');
                                foreach ($records as $record) {
                                    fputcsv($handle, [
                                        $record->codprod,
                                        $record->product_name,
                                        $record->product_name_en,
                                        $record->barcode,
                                        $record->curve,
                                        $record->import_status,
                                    ], ';');
                                }
                                fclose($handle);
                            }, 'produtos_exportacao_' . date('Y-m-d_H-i') . '.csv');
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('translate_hybrid')
                        ->label('Traduzir Selecionados (Auto)')
                        ->icon('heroicon-o-language')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Tradução Inteligente')
                        ->action(function (Collection $records) {
                            $service = app(GeminiFdaTranslator::class);
                            $processed = 0;
                            set_time_limit(300);

                            foreach ($records as $record) {
                                if ($record->product_name_en)
                                    continue;
                                $newName = $service->translate($record->product_name);
                                if ($newName) {
                                    $record->update(['product_name_en' => $newName]);
                                    $processed++;
                                    usleep(500000);
                                }
                            }
                            Notification::make()->title("{$processed} itens traduzidos.")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion()
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}