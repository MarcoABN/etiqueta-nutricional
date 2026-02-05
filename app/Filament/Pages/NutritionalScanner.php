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
                    ->disk('public')
                    ->extraInputAttributes(['capture' => 'environment', 'accept' => 'image/*'])
                    ->statePath('image_nutritional'),
            ])
            ->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first(); // Ajuste 'barcode' se o nome da coluna for 'codprod' ou outro

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
            
            // BLINDAGEM CONTRA DUPLICIDADE:
            // Usamos forceFill + saveQuietly para atualizar o banco SEM disparar 
            // eventos (Observers) que poderiam lançar jobs duplicados automaticamente.
            $this->foundProduct->forceFill([
                'image_nutritional' => $imagePath,
                'ai_status' => 'pending'
            ])->saveQuietly();

            Log::info("NutritionalScanner: Imagem salva, disparando Job MANUALMENTE para {$this->foundProduct->id}");

            // Disparo único e manual
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