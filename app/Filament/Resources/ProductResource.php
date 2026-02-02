<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Produtos';

    public static function form(Form $form): Form
    {
        return $form
            // Script de navegação por ENTER (Mantido pela produtividade)
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
                // --- TOPO: IDENTIFICAÇÃO E PORÇÕES ---
                Section::make('Identificação do Produto')
                    ->compact() // Remove margens excessivas nativamente
                    ->schema([
                        Forms\Components\Grid::make(12)
                            ->schema([
                                TextInput::make('codprod')->label('Cód. WinThor')->required()->columnSpan(2),
                                TextInput::make('product_name')->label('Nome do Produto')->required()->columnSpan(5),
                                TextInput::make('product_name_en')->label('Nome (Inglês)')->columnSpan(5),
                            ]),

                        Forms\Components\Grid::make(6) // 6 colunas para distribuir bem as porções
                            ->schema([
                                TextInput::make('servings_per_container')->label('Porções/Emb.')->columnSpan(1),
                                TextInput::make('serving_weight')->label('Peso Porção')->columnSpan(1),
                                TextInput::make('serving_size_quantity')->label('Qtd Medida')->columnSpan(2),
                                TextInput::make('serving_size_unit')->label('Unidade')->columnSpan(2),
                            ]),
                    ]),

                // --- MEIO: TABELA NUTRICIONAL (Lado a Lado) ---
                Section::make('Tabela Nutricional')
                    ->description('Fluxo: Coluna da Esquerda (Topo -> Baixo) depois Direita.')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(2) // Divide a tela ao meio
                            ->schema([

                                // === COLUNA DA ESQUERDA ===
                                Group::make()
                                    ->schema([
                                        // Valor Energético (Destaque)
                                        TextInput::make('calories')
                                            ->label('Valor energético (kcal)')
                                            ->extraInputAttributes(['class' => 'font-bold']), // Negrito para destaque visual

                                        // Grid interno para alinhar Valor | %VD
                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                TextInput::make('total_carb')->label('Carbo (g)')->columnSpan(3),
                                                TextInput::make('total_carb_dv')->label('%VD')->columnSpan(1),

                                                TextInput::make('total_sugars')->label('Açúcares Tot (g)')->columnSpan(4), // Ocupa tudo

                                                TextInput::make('added_sugars')->label('Açúcares Add (g)')->columnSpan(3),
                                                TextInput::make('added_sugars_dv')->label('%VD')->columnSpan(1),

                                                TextInput::make('protein')->label('Proteínas (g)')->columnSpan(3),
                                                TextInput::make('protein_dv')->label('%VD')->columnSpan(1),
                                            ]),
                                    ]),

                                // === COLUNA DA DIREITA ===
                                Group::make()
                                    ->schema([
                                        // Fieldset para organizar visualmente

                                        Forms\Components\Grid::make(4) // Grid de 4 colunas para alinhar Valor | %VD
                                            ->schema([
                                                // 1. CORREÇÃO: Adicionado o campo %VD de Gorduras Totais
                                                TextInput::make('total_fat')
                                                    ->label('Gorduras Totais (g)')
                                                    ->columnSpan(3),
                                                TextInput::make('total_fat_dv')
                                                    ->label('%VD')
                                                    ->columnSpan(1),

                                                // Gorduras Saturadas
                                                TextInput::make('sat_fat')->label('Gord. Sat (g)')->columnSpan(3),
                                                TextInput::make('sat_fat_dv')->label('%VD')->columnSpan(1),

                                                // Gorduras Trans
                                                TextInput::make('trans_fat')->label('Gord. Trans (g)')->columnSpan(3),
                                                TextInput::make('trans_fat_dv')->label('%VD')->columnSpan(1),

                                                // Fibras
                                                TextInput::make('fiber')->label('Fibras (g)')->columnSpan(3),
                                                TextInput::make('fiber_dv')->label('%VD')->columnSpan(1),

                                                // Sódio
                                                TextInput::make('sodium')->label('Sódio (mg)')->columnSpan(3),
                                                TextInput::make('sodium_dv')->label('%VD')->columnSpan(1),

                                                // 2. CORREÇÃO DO ERRO SQL (Colesterol)
                                                // Adicionamos 'dehydrateStateUsing' para garantir que nunca envie NULL
                                                TextInput::make('cholesterol')
                                                    ->label('Colesterol (mg)')
                                                    ->default('0')
                                                    ->dehydrateStateUsing(fn($state) => $state === null || $state === '' ? '0' : $state)
                                                    ->columnSpan(3),

                                                TextInput::make('cholesterol_dv')->label('%VD')->columnSpan(1),
                                            ]),
                                    ])->columns(1),

                            ]),
                    ]),

                // --- BAIXO: TEXTOS LEGAIS ---
                Section::make('Rotulagem e Ingredientes')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Textarea::make('ingredients')
                                    ->label('Lista de Ingredientes (Inglês)')
                                    ->rows(4)
                                    ->columnSpan(2), // Ocupa 2/3 da largura

                                Group::make() // Alérgicos na lateral direita do texto
                                    ->schema([
                                        TextInput::make('allergens_contains')->label('CONTÉM (Alérgicos)'),
                                        TextInput::make('allergens_may_contain')->label('PODE CONTER'),
                                    ])->columnSpan(1),
                            ]),
                    ]),

                // --- RODAPÉ: MICRONUTRIENTES (Colapsado) ---
                Section::make('Micronutrientes')
                    ->collapsed()
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(6) // Grid de 6 mantém organizado sem apertar demais
                            ->schema([
                                TextInput::make('vitamin_d')->label('Vit D'),
                                TextInput::make('calcium')->label('Cálcio'),
                                TextInput::make('iron')->label('Ferro'),
                                TextInput::make('potassium')->label('Potássio'),
                                TextInput::make('vitamin_a')->label('Vit A'),
                                TextInput::make('vitamin_c')->label('Vit C'),
                                TextInput::make('vitamin_e')->label('Vit E'),
                                TextInput::make('vitamin_k')->label('Vit K'),
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
                Tables\Columns\TextColumn::make('sku')
                    ->label('Cód.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->limit(50), // Limita texto longo

                Tables\Columns\TextColumn::make('price')
                    ->label('Preço')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // --- AQUI ESTÁ A MÁGICA DA IMPRESSÃO ---
                Action::make('imprimir')
                    ->label('Etiqueta')
                    ->icon('heroicon-o-printer')
                    ->color('success') // Botão Verde
                    ->url(fn(Product $record) => route('imprimir.etiqueta', $record))
                    ->openUrlInNewTab() // Abre nova aba -> Chrome Kiosk imprime -> Fecha
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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