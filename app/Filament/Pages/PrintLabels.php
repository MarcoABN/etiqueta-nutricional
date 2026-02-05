<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PrintLabels extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'Imprimir Etiquetas';
    protected static ?string $title = 'Central de Impressão FDA';
    protected static string $view = 'filament.pages.print-labels';

    // Propriedades do Livewire
    public ?string $search_code = '';
    public int $quantity = 1;
    public ?Product $product = null;

    protected function getViewData(): array
    {
        return [
            'settings' => \App\Models\LabelSetting::firstOrCreate(['id' => 1]),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // 1. Campo de Busca (2 Colunas)
                TextInput::make('search_code')
                    ->label('Pesquisar Cód. WinThor')
                    ->placeholder('Bipe ou digite...')
                    ->autofocus()
                    ->required()
                    ->suffixAction(
                        Action::make('search')
                            ->icon('heroicon-m-magnifying-glass')
                            ->action(fn () => $this->searchProduct())
                    )
                    ->extraInputAttributes(['wire:keydown.enter' => 'searchProduct'])
                    ->columnSpan(2), 

                // 2. Visualização do Status (1 Coluna - NOVO)
                Placeholder::make('status_display')
                    ->label('Status do Produto')
                    ->content(function () {
                        if (!$this->product) return '-';
                        
                        $status = $this->product->import_status ?? 'Indefinido';
                        $color = match ($status) {
                            'Liberado' => 'text-green-500',
                            'Processado (IA)' => 'text-blue-400', // Azul para destacar IA
                            'Em Análise' => 'text-yellow-500',
                            'Bloqueado' => 'text-red-500',
                            default => 'text-gray-400',
                        };

                        // Retorna HTML colorido e negrito
                        return new HtmlString("<span class='text-xl font-black {$color}'>{$status}</span>");
                    })
                    ->columnSpan(1),

                // 3. Quantidade (1 Coluna)
                TextInput::make('quantity')
                    ->label('Qtd. Etiquetas')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(1000)
                    ->required()
                    ->live()
                    ->columnSpan(1),
            ])->columns(4); // Grid total de 4 colunas
    }

    public function searchProduct()
    {
        $this->validate(['search_code' => 'required']);

        $found = Product::where('codprod', $this->search_code)->first();

        if ($found) {
            $this->product = $found;
            Notification::make()->title('Produto Carregado')->success()->send();
        } else {
            $this->product = null;
            Notification::make()->title('Produto não encontrado')->danger()->send();
        }
    }
}