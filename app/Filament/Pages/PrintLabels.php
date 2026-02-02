<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
// use Illuminate\Contracts\View\View; // Pode remover se não for usar em outros lugares

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

    // --- CORREÇÃO AQUI ---
    // Em vez de render(), usamos getViewData() para passar variáveis para o Blade
    // mantendo o layout do Filament.
    protected function getViewData(): array
    {
        return [
            'settings' => \App\Models\LabelSetting::firstOrCreate(['id' => 1]),
        ];
    }
    // ---------------------

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('search_code')
                    ->label('Pesquisar Cód. WinThor')
                    ->placeholder('Bipe aqui...')
                    ->autofocus()
                    ->required()
                    ->suffixAction(
                        Action::make('search')
                            ->icon('heroicon-m-magnifying-glass')
                            ->action(fn () => $this->searchProduct())
                    )
                    ->extraInputAttributes(['wire:keydown.enter' => 'searchProduct'])
                    ->columnSpan(3), 

                TextInput::make('quantity')
                    ->label('Qtd.')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(1000)
                    ->required()
                    ->columnSpan(1),
            ])->columns(4);
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