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
    protected static ?string $title = ''; // Sem título para limpar a tela
    protected static string $view = 'filament.pages.nutritional-scanner';

    // Variáveis Públicas
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
                    ->hiddenLabel()
                    ->image()
                    // --- OTIMIZAÇÃO DE IMAGEM (1080p) ---
                    ->imageResizeMode('contain')
                    ->imageCropAspectRatio('1:1') // Opcional: força quadrado ou remove para livre
                    ->imageResizeTargetWidth(1080)
                    ->imageResizeTargetHeight(1920) // Limite vertical também
                    ->imageResizeUpscale(false) // Não aumenta imagens pequenas
                    ->optimize('webp') // Converte para WebP (muito mais leve)
                    // ------------------------------------
                    ->directory('uploads/nutritional')
                    ->required()
                    ->extraInputAttributes([
                        'capture' => 'environment', // Abre câmera traseira
                        'accept' => 'image/*'
                    ])
                    ->panelLayout('integrated') // Layout mais limpo
                    ->removeUploadedFileButtonPosition('right')
                    ->uploadButtonPosition('left')
                    ->uploadProgressIndicatorPosition('left')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            // Preenche imagem se já existir (para visualização)
            $this->form->fill([
                'image_nutritional' => $product->image_nutritional,
            ]);
        } else {
            $this->foundProduct = null;
            Notification::make()->title('EAN não cadastrado: ' . $code)->danger()->send();
        }
    }

    public function save()
    {
        $data = $this->form->getState();

        if ($this->foundProduct) {
            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()->title('Salvo!')->success()->send();
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