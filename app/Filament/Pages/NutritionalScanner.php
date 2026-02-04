<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class NutritionalScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Coletor Nutricional';
    protected static ?string $title = ''; 
    protected static string $view = 'filament.pages.nutritional-scanner';

    public $scannedCode = null;
    public $foundProduct = null;
    public ?array $data = [];

    // Listener para resetar via JS se necessário
    protected $listeners = ['reset-scanner-ui' => 'resetScanner'];

    public function mount(): void 
    { 
        $this->form->fill(); 
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('image_nutritional')
                    ->hiddenLabel()
                    ->image()
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1280)
                    ->imageResizeTargetHeight(1280)
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    ->extraInputAttributes([
                        'capture' => 'environment',
                        'accept' => 'image/*'
                    ])
                    // REMOVIDO ->live() para evitar refresh da tela
                    // REMOVIDO ->afterStateUpdated() pois controlamos via JS
                    ->statePath('image_nutritional'),
            ])
            ->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            $this->data['image_nutritional'] = null;
            $this->form->fill($this->data); 
        } else {
            $this->foundProduct = null;
            Notification::make()->title('EAN não cadastrado')->danger()->send();
            $this->dispatch('reset-scanner');
        }
    }

    public function save()
    {
        $state = $this->form->getState();
        
        // Verifica se a imagem chegou (pode vir como array ou string dependendo do adapter)
        $image = $state['image_nutritional'] ?? null;
        if (is_array($image)) {
            $image = array_values($image)[0];
        }

        if ($this->foundProduct && !empty($image)) {
            $this->foundProduct->update(['image_nutritional' => $image]);
            
            Notification::make()->title('Salvo com sucesso!')->success()->send();
            $this->resetScanner();
        } else {
            Notification::make()->title('Erro: Nenhuma imagem detectada')->warning()->send();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->data = [];
        $this->form->fill();
        $this->dispatch('reset-scanner'); 
    }
}