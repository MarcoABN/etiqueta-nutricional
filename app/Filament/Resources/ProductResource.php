<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Services\GeminiFdaTranslator; // O Serviço de Tradução
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Produtos';

    public static function form(Form $form): Form
    {
        return $form
            // Navegação por ENTER
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
                // --- TOPO: IDENTIFICAÇÃO ---
                Section::make('Identificação do Produto')
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

                                TextInput::make('product_name')
                                    ->label('Nome do Produto')
                                    ->required()
                                    ->columnSpan(7),

                                TextInput::make('product_name_en')
                                    ->label('Nome (Inglês - Tradução)')
                                    ->columnSpan(12),
                            ]),

                        Forms\Components\Grid::make(6)
                            ->schema([
                                TextInput::make('servings_per_container')->label('Porções/Emb.')->columnSpan(1),
                                TextInput::make('serving_weight')->label('Peso Porção')->columnSpan(1),
                                TextInput::make('serving_size_quantity')->label('Qtd Medida')->columnSpan(2),
                                TextInput::make('serving_size_unit')->label('Unidade')->columnSpan(2),
                            ]),
                    ]),

                // --- MEIO: TABELA NUTRICIONAL ---
                Section::make('Tabela Nutricional')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Group::make()->schema([
                                    TextInput::make('calories')
                                        ->label('Valor energético (kcal)')
                                        ->extraInputAttributes(['class' => 'font-bold']),

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

                // --- BAIXO: TEXTOS LEGAIS ---
                Section::make('Rotulagem e Ingredientes')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Textarea::make('ingredients')
                                ->label('Lista de Ingredientes (Inglês)')
                                ->rows(4)
                                ->columnSpan(2),
                            Group::make()->schema([
                                TextInput::make('allergens_contains')->label('CONTÉM (Alérgicos)'),
                                TextInput::make('allergens_may_contain')->label('PODE CONTER'),
                            ])->columnSpan(1),
                        ]),
                    ]),

                // --- MICRONUTRIENTES ---
                Section::make('Micronutrientes')
                    ->collapsed()
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(6)->schema([
                            TextInput::make('vitamin_d')->label('Vit D'),
                            TextInput::make('calcium')->label('Cálcio'),
                            TextInput::make('iron')->label('Ferro'),
                            TextInput::make('potassium')->label('Potássio'),
                            TextInput::make('vitamin_a')->label('Vit A'),
                            TextInput::make('vitamin_c')->label('Vit C'),
                            TextInput::make('vitamin_e')->label('Vit E'),
                            TextInput::make('thiamin')->label('Tiamina'),
                            TextInput::make('riboflavin')->label('Ribofl.'),
                            TextInput::make('niacin')->label('Niacina'),
                            TextInput::make('vitamin_b6')->label('Vit B6'),
                            TextInput::make('folate')->label('Folato'),
                            TextInput::make('vitamin_b12')->label('Vit B12'),
                            TextInput::make('biotin')->label('Biotina'),
                            TextInput::make('pantothenic_acid')->label('Pantot.'),
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codprod')
                    ->label('Cód. WinThor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('barcode')
                    ->label('EAN/GTIN')
                    ->searchable()
                    ->sortable()
                    ->color('gray')
                    ->copyable(),

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produto')
                    ->searchable()
                    ->description(fn(Product $record) => $record->product_name_en)
                    ->limit(50),

                Tables\Columns\TextColumn::make('calories')
                    ->label('Kcal')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    // AÇÃO DE PRODUÇÃO (Manual)
                    BulkAction::make('translate_manual')
                        ->label('Traduzir Selecionados (IA)')
                        ->icon('heroicon-o-language')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Traduzir Produtos')
                        ->modalDescription('Use esta opção para traduzir poucos itens (ex: novos cadastros). Para traduzir todo o banco, use o comando via terminal para não travar seu navegador.')
                        ->action(function (Collection $records) {

                            $service = new GeminiFdaTranslator();
                            $processed = 0;

                            foreach ($records as $record) {
                                if (!$record->product_name)
                                    continue;

                                // Só traduz se estiver vazio ou se o usuário forçou a ação
                                $newName = $service->translate($record->product_name);

                                if ($newName) {
                                    $record->update(['product_name_en' => $newName]);
                                    $processed++;
                                }

                                // Delay de segurança
                                sleep(4);
                            }

                            Notification::make()
                                ->title("Concluído")
                                ->body("{$processed} itens traduzidos.")
                                ->success()
                                ->send();
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