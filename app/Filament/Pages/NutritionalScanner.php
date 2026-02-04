<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessProductImage; // Importar o Job
use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

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
                    // REMOVIDO o resize no upload para preservar a qualidade do Crop feito no JS
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
        
        $imagePath = $state['image_nutritional'] ?? null;
        if (is_array($imagePath)) {
            $imagePath = array_values($imagePath)[0];
        }

        if ($this->foundProduct && !empty($imagePath)) {
            // 1. Atualiza o produto com o caminho da imagem
            $this->foundProduct->update([
                'image_nutritional' => $imagePath,
                'ai_status' => 'pending' // Marca como pendente para feedback visual
            ]);
            
            // 2. DISPARA O JOB IMEDIATAMENTE (Correção Vital)
            ProcessProductImage::dispatch($this->foundProduct);

            Notification::make()->title('Processamento iniciado!')->success()->send();
            
            // Opcional: Não resetar imediatamente para o usuário ver que foi enviado
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