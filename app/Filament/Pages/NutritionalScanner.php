<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Jobs\ProcessProductImage; // Importante para despachar o Job manualmente

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

    public function mount(): void 
    { 
        $this->form->fill(); 
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('image_nutritional')
                    ->label('Foto da Tabela') // Label visível ajuda a entender
                    ->image()
                    
                    // --- CONFIGURAÇÃO DO CROP ---
                    ->imageEditor()
                    ->imageEditorMode(2) // Modal
                    ->imageCropAspectRatio(null) // Livre
                    
                    // --- REMOVIDO O LIVE E O AFTER STATE UPDATED ---
                    // Isso impede que o formulário seja processado assim que a foto é tirada.
                    // O usuário precisa clicar em "Enviar" depois de editar.
                    // ->live() 
                    // ->afterStateUpdated(...) 
                    
                    ->imageResizeTargetWidth('2000')
                    ->imageResizeTargetHeight('2000')
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    ->extraInputAttributes([
                        'capture' => 'environment',
                        'accept' => 'image/*'
                    ])
                    ->statePath('image_nutritional'),
            ])
            ->statePath('data');
    }

    // Ação que será chamada pelo botão na View
    public function processImage()
    {
        $state = $this->form->getState();

        if ($this->foundProduct && !empty($state['image_nutritional'])) {
            
            // 1. Salva a imagem no produto
            $this->foundProduct->update([
                'image_nutritional' => $state['image_nutritional']
            ]);

            // 2. Dispara o Job de IA manualmente
            ProcessProductImage::dispatch($this->foundProduct);

            Notification::make()
                ->title('Imagem enviada! Processando IA...')
                ->success()
                ->send();

            // 3. Reinicia o scanner
            $this->resetScanner();
        } else {
            Notification::make()
                ->title('Tire a foto e faça o recorte antes de enviar.')
                ->warning()
                ->send();
        }
    }
    
    // ... (Mantenha handleBarcodeScan e resetScanner iguais)
    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            $this->form->fill(['image_nutritional' => null]); 
        } else {
            $this->foundProduct = null;
            Notification::make()->title('EAN não cadastrado')->danger()->send();
            $this->dispatch('reset-scanner');
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