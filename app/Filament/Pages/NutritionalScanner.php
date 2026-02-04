<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Jobs\ProcessProductImage;
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
    protected static ?string $navigationLabel = 'Coletor';
    protected static ?string $title = ''; 
    protected static string $view = 'filament.pages.nutritional-scanner';

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
                    ->imageEditor()
                    ->imageEditorMode(2)
                    // Ativa o CROP LIVRE
                    ->imageCropAspectRatio(null)
                    ->imageEditorAspectRatios([null])
                    ->imageResizeTargetWidth('2000')
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

    public function processImage()
    {
        $state = $this->form->getState();

        if (!$this->foundProduct || empty($state['image_nutritional'])) {
            Notification::make()->title('Erro: Tire a foto primeiro.')->danger()->send();
            return;
        }

        $this->foundProduct->update([
            'image_nutritional' => $state['image_nutritional']
        ]);

        ProcessProductImage::dispatch($this->foundProduct);

        Notification::make()->title('Processando via IA...')->success()->send();
        $this->resetScanner();
    }

    public function handleBarcodeScan($code)
    {
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            $this->form->fill(['image_nutritional' => null]); 
        } else {
            Notification::make()->title("EAN {$code} não encontrado.")->danger()->send();
            $this->dispatch('reset-scanner');
        }
    }

    public function resetScanner()
    {
        $this->foundProduct = null;
        $this->data = [];
        $this->form->fill();
        $this->dispatch('reset-scanner'); 
    }
}