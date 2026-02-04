<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class NutritionalScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Coletor Nutricional';
    
    // Deixamos vazio para o CSS do Blade assumir o controle do layout
    protected static ?string $title = ''; 
    
    protected static string $view = 'filament.pages.nutritional-scanner';

    // --- VARIÁVEIS PÚBLICAS (Essenciais para o Blade não quebrar) ---
    public $scannedCode = null;
    public $foundProduct = null;
    public ?array $data = []; 
    // ----------------------------------------------------------------

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Foto da Tabela Nutricional')
                    ->schema([
                        FileUpload::make('image_nutritional')
                            ->label('Câmera / Arquivo')
                            ->image()
                            ->imageEditor()
                            ->imageEditorMode(2)
                            ->directory('uploads/nutritional')
                            ->required()
                            ->columnSpanFull(),
                    ])
            ])
            ->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            $this->form->fill([
                'image_nutritional' => $product->image_nutritional,
            ]);
        } else {
            $this->foundProduct = null;
            Notification::make()->title('EAN desconhecido: ' . $code)->warning()->send();
        }
    }

    public function save()
    {
        $data = $this->form->getState();

        if ($this->foundProduct) {
            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()->title('Foto salva!')->success()->send();
            $this->resetScanner();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->form->fill();
        $this->dispatch('reset-scanner'); 
    }
}