<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Services\GeminiFdaTranslator;
use App\Services\OpenFoodFactsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Produtos';
    protected static ?string $modelLabel = 'Produto';
    protected static ?string $pluralModelLabel = 'Produtos';
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->extraAttributes([
                'class' => 'nx-compact-form',
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
                Forms\Components\Placeholder::make('estilos_compactos')
                    ->hiddenLabel()
                    ->content(new HtmlString('
                        <style>
                            .nx-compact-form .grid { gap: 0.75rem !important; }
                            .nx-compact-form .fi-fo-field-wrp { gap: 0.15rem !important; }
                            .nx-compact-form .fi-fo-field-wrp-label span { font-size: 0.8rem !important; }
                            .nx-compact-form .fi-input-wrp input,
                            .nx-compact-form .fi-select-input {
                                padding-top: 0.35rem !important;
                                padding-bottom: 0.35rem !important;
                                height: 2.1rem !important;
                                min-height: 2.1rem !important;
                                font-size: 0.85rem !important;
                            }
                            .nx-compact-form .fi-section-content { padding: 1rem !important; }
                            .nx-compact-form .fi-input-sfx button {
                                padding: 0.25rem !important;
                                height: auto !important;
                            }
                        </style>
                    '))
                    ->columnSpanFull(),

                Section::make('Dados Principais do Produto')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(12)
                            ->schema([
                                TextInput::make('codprod')
                                    ->label('Cód. WinThor')
                                    ->required()
                                    ->numeric()
                                    ->unique(ignoreRecord: true)
                                    ->columnSpan(['default' => 12, 'md' => 3]),

                                TextInput::make('barcode')
                                    ->label('Cód. Barras')
                                    ->unique(ignoreRecord: true)
                                    ->columnSpan(['default' => 12, 'md' => 3])
                                    ->suffixAction(self::getOpenFoodFactsAction()),

                                Select::make('curve')
                                    ->label('Curva')
                                    ->options([
                                        'A' => 'Curva A',
                                        'B' => 'Curva B',
                                        'C' => 'Curva C',
                                    ])
                                    ->columnSpan(['default' => 12, 'md' => 2]),

                                Select::make('import_status')
                                    ->label('Status')
                                    ->options([
                                        'Bloqueado' => 'Bloqueado',
                                        'Dados via API' => 'Dados via API',
                                        'Processado (IA)' => 'Processado (IA)',
                                        'Em Análise' => 'Em Análise',
                                        'Liberado' => 'Liberado',
                                    ])
                                    ->default('Bloqueado')
                                    ->required()
                                    ->selectablePlaceholder(false)
                                    ->columnSpan(['default' => 12, 'md' => 4]),

                                TextInput::make('product_name')
                                    ->label('Nome do Produto')
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 6]),

                                TextInput::make('product_name_en')
                                    ->label('Nome (Inglês - Tradução)')
                                    ->columnSpan(['default' => 12, 'md' => 6]),

                                TextInput::make('qtunitcx')
                                    ->label('Qtd. Unit. na Caixa')
                                    ->numeric()
                                    ->columnSpan(['default' => 12, 'md' => 3]),

                                TextInput::make('pesoliq')
                                    ->label('Peso Líquido')
                                    ->numeric()
                                    ->columnSpan(['default' => 12, 'md' => 3]),

                                TextInput::make('unidade')
                                    ->label('Unidade')
                                    ->default('CX')
                                    ->columnSpan(['default' => 12, 'md' => 3]),

                                TextInput::make('ncm')
                                    ->label('NCM')
                                    ->numeric()
                                    ->maxLength(8)
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                            ]),
                    ]),

                Section::make('Tabela Nutricional (Macronutrientes)')
                    ->compact()
                    ->headerActions([
                        // NOVA FUNÇÃO: Botão de Clonar Tabela Nutricional
                        FormAction::make('replicar_tabela')
                            ->label('Copiar de Outro Produto')
                            ->icon('heroicon-m-document-duplicate')
                            ->color('gray')
                            ->size(ActionSize::Small)
                            ->form([
                                TextInput::make('source_codprod')
                                    ->label('Cód. WinThor de Origem')
                                    ->required()
                                    ->numeric()
                                    ->autofocus()
                                    ->placeholder('Ex: 12345'),
                            ])
                            ->action(function (array $data, Set $set) {
                                $sourceProduct = Product::where('codprod', $data['source_codprod'])->first();

                                if (!$sourceProduct) {
                                    Notification::make()
                                        ->title('Produto não encontrado')
                                        ->body('Verifique se o Cód. WinThor está correto.')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                // Lista de todos os campos que serão puxados
                                $camposNutricionais = [
                                    // Porções e Medidas
                                    'servings_per_container',
                                    'serving_weight',
                                    'serving_size_quantity',
                                    'serving_size_unit',
                                    // Macronutrientes
                                    'calories',
                                    'total_carb',
                                    'total_carb_dv',
                                    'total_sugars',
                                    'added_sugars',
                                    'added_sugars_dv',
                                    'protein',
                                    'protein_dv',
                                    'total_fat',
                                    'total_fat_dv',
                                    'sat_fat',
                                    'sat_fat_dv',
                                    'trans_fat',
                                    'trans_fat_dv',
                                    'fiber',
                                    'fiber_dv',
                                    'sodium',
                                    'sodium_dv',
                                    'cholesterol',
                                    'cholesterol_dv',
                                    // Rotulagem e Alergênicos
                                    'ingredients',
                                    'allergens_contains',
                                    'allergens_may_contain',
                                    // Micronutrientes
                                    'vitamin_d',
                                    'calcium',
                                    'iron',
                                    'potassium',
                                    'vitamin_a',
                                    'vitamin_c',
                                    'vitamin_e',
                                    'vitamin_k',
                                    'thiamin',
                                    'riboflavin',
                                    'niacin',
                                    'vitamin_b6',
                                    'folate',
                                    'vitamin_b12',
                                    'biotin',
                                    'pantothenic_acid',
                                    'phosphorus',
                                    'iodine',
                                    'magnesium',
                                    'zinc',
                                    'selenium',
                                    'copper',
                                    'manganese',
                                    'chromium',
                                    'molybdenum',
                                    'chloride'
                                ];

                                foreach ($camposNutricionais as $campo) {
                                    $set($campo, $sourceProduct->{$campo});
                                }

                                Notification::make()
                                    ->title('Dados copiados com sucesso!')
                                    ->success()
                                    ->send();
                            })
                    ])
                    ->schema([
                        Forms\Components\Grid::make(['default' => 2, 'sm' => 3, 'md' => 6])
                            ->schema([
                                TextInput::make('servings_per_container')->label('Porções/Emb.')->numeric()->columnSpan(['default' => 1, 'md' => 1]),
                                TextInput::make('serving_weight')->label('Peso Porção')->columnSpan(['default' => 1, 'md' => 1]),
                                TextInput::make('serving_size_quantity')->label('Qtd Medida')->columnSpan(['default' => 1, 'sm' => 2, 'md' => 2]),
                                TextInput::make('serving_size_unit')->label('Unidade')->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2]),
                            ])->extraAttributes(['class' => 'mb-4 border-b pb-4']),

                        Forms\Components\Grid::make(['default' => 1, 'lg' => 2])
                            ->schema([
                                Group::make()->schema([
                                    TextInput::make('calories')
                                        ->label('Valor energético (kcal)')
                                        ->extraInputAttributes(['class' => 'font-bold text-lg']),

                                    Forms\Components\Grid::make(['default' => 4])->schema([
                                        TextInput::make('total_carb')->label('Carbo (g)')->columnSpan(['default' => 3]),
                                        TextInput::make('total_carb_dv')->label('%VD')->columnSpan(['default' => 1]),

                                        TextInput::make('total_sugars')->label('Açúcares Tot (g)')->columnSpan(['default' => 4]),

                                        TextInput::make('added_sugars')->label('Açúcares Add (g)')->columnSpan(['default' => 3]),
                                        TextInput::make('added_sugars_dv')->label('%VD')->columnSpan(['default' => 1]),

                                        TextInput::make('protein')->label('Proteínas (g)')->columnSpan(['default' => 3]),
                                        TextInput::make('protein_dv')->label('%VD')->columnSpan(['default' => 1]),
                                    ]),
                                ]),

                                Group::make()->schema([
                                    Forms\Components\Grid::make(['default' => 4])->schema([
                                        TextInput::make('total_fat')->label('Gorduras Totais (g)')->columnSpan(['default' => 3]),
                                        TextInput::make('total_fat_dv')->label('%VD')->columnSpan(['default' => 1]),

                                        TextInput::make('sat_fat')->label('Gord. Sat (g)')->columnSpan(['default' => 3]),
                                        TextInput::make('sat_fat_dv')->label('%VD')->columnSpan(['default' => 1]),

                                        TextInput::make('trans_fat')->label('Gord. Trans (g)')->columnSpan(['default' => 3]),
                                        TextInput::make('trans_fat_dv')->label('%VD')->columnSpan(['default' => 1]),

                                        TextInput::make('fiber')->label('Fibras (g)')->columnSpan(['default' => 3]),
                                        TextInput::make('fiber_dv')->label('%VD')->columnSpan(['default' => 1]),

                                        TextInput::make('sodium')->label('Sódio (mg)')->columnSpan(['default' => 3]),
                                        TextInput::make('sodium_dv')->label('%VD')->columnSpan(['default' => 1]),

                                        TextInput::make('cholesterol')->label('Colesterol (mg)')->columnSpan(['default' => 3]),
                                        TextInput::make('cholesterol_dv')->label('%VD')->columnSpan(['default' => 1]),
                                    ]),
                                ]),
                            ]),
                    ]),

                Section::make('Rotulagem e Ingredientes')
                    ->compact()
                    ->schema([
                        Group::make()->schema([
                            Textarea::make('ingredients')
                                ->label('Lista de Ingredientes (Original/EN)')
                                ->placeholder('Ingredientes originais ou em inglês...')
                                ->rows(3),

                            Forms\Components\Grid::make(2)->schema([
                                TextInput::make('allergens_contains')
                                    ->label('CONTÉM (EN)'),

                                TextInput::make('allergens_may_contain')
                                    ->label('PODE CONTER (EN)'),
                            ]),
                        ])->columnSpanFull(),
                    ]),

                Section::make('Micronutrientes (Vitaminas e Minerais)')
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(['default' => 2, 'md' => 4])->schema([
                            TextInput::make('vitamin_d')->label('Vit D (mcg)'),
                            TextInput::make('calcium')->label('Cálcio (mg)'),
                            TextInput::make('iron')->label('Ferro (mg)'),
                            TextInput::make('potassium')->label('Potássio (mg)'),
                            TextInput::make('vitamin_a')->label('Vit A (mcg)'),
                            TextInput::make('vitamin_c')->label('Vit C (mg)'),
                            TextInput::make('vitamin_e')->label('Vit E (mg)'),
                            TextInput::make('vitamin_k')->label('Vit K (mcg)'),
                            TextInput::make('thiamin')->label('Tiamina B1 (mg)'),
                            TextInput::make('riboflavin')->label('Riboflavina B2 (mg)'),
                            TextInput::make('niacin')->label('Niacina B3 (mg)'),
                            TextInput::make('vitamin_b6')->label('Vit B6 (mg)'),
                            TextInput::make('folate')->label('Folato (mcg)'),
                            TextInput::make('vitamin_b12')->label('Vit B12 (mcg)'),
                            TextInput::make('biotin')->label('Biotina (mcg)'),
                            TextInput::make('pantothenic_acid')->label('Ác. Pantotênico (mg)'),
                            TextInput::make('phosphorus')->label('Fósforo (mg)'),
                            TextInput::make('iodine')->label('Iodo (mcg)'),
                            TextInput::make('magnesium')->label('Magnésio (mg)'),
                            TextInput::make('zinc')->label('Zinco (mg)'),
                            TextInput::make('selenium')->label('Selênio (mcg)'),
                            TextInput::make('copper')->label('Cobre (mcg)'),
                            TextInput::make('manganese')->label('Manganês (mg)'),
                            TextInput::make('chromium')->label('Cromo (mcg)'),
                            TextInput::make('molybdenum')->label('Molibdênio (mcg)'),
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

    public static function getOpenFoodFactsAction(): FormAction
    {
        return FormAction::make('searchApi')
            ->icon('heroicon-o-cloud-arrow-down')
            ->tooltip('Buscar dados na Open Food Facts')
            ->color('info')
            ->action(function ($state, Set $set, $record) {
                $barcode = $state ?? $record?->barcode;

                if (!$barcode) {
                    Notification::make()->title('Preencha o código de barras primeiro')->warning()->send();
                    return;
                }

                $service = new OpenFoodFactsService();
                $data = $service->fetchProductData($barcode);

                if (!$data) {
                    Notification::make()->title('Produto não encontrado na API')->danger()->send();
                    return;
                }

                foreach ($data as $key => $value) {
                    if ($value !== null) {
                        if ($key === 'image_nutritional') {
                            $set($key, [$value]);
                        } else {
                            $set($key, $value);
                        }
                    }
                }
                Notification::make()->title('Dados importados com sucesso!')->success()->send();
            });
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

                Tables\Columns\TextColumn::make('pesoliq')
                    ->label('Peso Líquido')
                    ->sortable()
                    ->numeric(2, ',', '.')
                    ->searchable(),

                Tables\Columns\TextColumn::make('qtunitcx')
                    ->label('Qtd Cx')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (blank($state)) return '-';
                        $formatado = number_format((float) $state, 2, ',', '.');
                        return str_replace(',00', '', $formatado);
                    })
                    ->copyable()
                    ->copyableState(function ($state) {
                        if (blank($state)) return '';
                        $formatado = number_format((float) $state, 2, ',', '.');
                        return str_replace(',00', '', $formatado);
                    })
                    ->copyMessage('Copiado!')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('import_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Liberado' => 'success',
                        'Dados via API' => 'info',
                        'Processado (IA)' => 'primary',
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
                    ->label('Status')
                    ->options([
                        'Liberado' => 'Liberado',
                        'Dados via API' => 'Dados via API',
                        'Processado (IA)' => 'Processado (IA)',
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
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('sync_api')
                        ->label('Buscar Dados na API (Lote)')
                        ->icon('heroicon-o-cloud-arrow-down')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Atenção')
                        ->modalDescription('Isso irá buscar dados na OpenFoodFacts para os itens selecionados. Dados existentes podem ser sobrescritos.')
                        ->action(function (Collection $records) {
                            $service = new OpenFoodFactsService();
                            $count = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if (!$record->barcode) {
                                    continue;
                                }

                                $data = $service->fetchProductData($record->barcode);

                                if ($data) {
                                    $record->update($data);
                                    $count++;
                                    usleep(200000);
                                } else {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title("Processamento Finalizado")
                                ->body("{$count} atualizados. {$failed} não encontrados ou sem código.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('export_csv')
                        ->label('Exportar CSV Selecionados')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            return response()->streamDownload(function () use ($records) {
                                echo "\xEF\xBB\xBF";
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['Cod. WinThor', 'Produto', 'Traducao', 'EAN', 'Curva', 'Status'], ';');
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
                        ->color('primary')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
