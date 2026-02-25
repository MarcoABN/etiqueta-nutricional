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
use Illuminate\Support\Facades\Log;

class NutritionalScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Coletor Nutricional';
    protected static ?string $title = ''; 
    protected static string $view = 'filament.pages.nutritional-scanner';

    protected static ?string $navigationGroup = 'Etiquetas';
    protected static ?int $navigationSort = 3;

    public $scannedCode = null;
    public $foundProduct = null;
    public ?array $data = [];

    // Listener para resetar via frontend se necessário
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
                    ->disk('public')
                    // Capture environment força a câmera traseira em mobiles no input file
                    ->extraInputAttributes(['capture' => 'environment', 'accept' => 'image/*'])
                    ->statePath('image_nutritional'),
            ])
            ->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        // Ajuste conforme sua coluna real no banco (ex: 'barcode', 'ean', 'codprod')
        $product = Product::where('barcode', $code)->first(); 

        if ($product) {
            $this->foundProduct = $product;
            $this->data['image_nutritional'] = null;
            $this->form->fill($this->data); 
        } else {
            $this->foundProduct = null;
            Notification::make()->title('EAN não cadastrado')->danger()->send();
            
            // Dispara evento para reabrir a câmera no frontend
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
            
            $this->foundProduct->forceFill([
                'image_nutritional' => $imagePath,
                'ai_status' => 'pending'
            ])->saveQuietly();

            Log::info("NutritionalScanner: Imagem salva, disparando Job MANUALMENTE para {$this->foundProduct->id}");

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
        
        // Comando para o frontend reiniciar a câmera
        $this->dispatch('reset-scanner'); 
    }
}