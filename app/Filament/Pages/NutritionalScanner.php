<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessProductImage;
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
                    ->directory('uploads/nutritional')
                    ->disk('public') // Garante disco público
                    ->extraInputAttributes(['capture' => 'environment', 'accept' => 'image/*'])
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
            $this->data['image_nutritional'] = null; // Limpa para nova foto
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
        
        // Extrai o caminho da imagem corretamente
        $imagePath = $state['image_nutritional'] ?? null;
        if (is_array($imagePath)) {
            $imagePath = array_values($imagePath)[0];
        }

        if ($this->foundProduct && !empty($imagePath)) {
            
            // 1. Persiste o caminho no banco primeiro
            $this->foundProduct->update([
                'image_nutritional' => $imagePath,
                'ai_status' => 'pending'
            ]);

            // 2. Dispara o Job
            // Usamos dispatchAfterResponse para garantir que o upload terminou
            ProcessProductImage::dispatch($this->foundProduct);

            Notification::make()
                ->title('Imagem enviada!')
                ->body('O processamento da IA iniciou em segundo plano.')
                ->success()
                ->send();
            
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