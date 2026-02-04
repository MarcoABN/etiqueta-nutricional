<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

    // Listener para feedback visual vindo do JS
    protected $listeners = ['file-uploaded-callback' => 'onFileUploaded'];

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
                    // O atributo capture é mantido para compatibilidade, 
                    // mas o trigger principal agora é feito via JS no Blade
                    ->extraInputAttributes([
                        'capture' => 'environment',
                        'accept' => 'image/*'
                    ])
                    ->live() 
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
            // Limpa qualquer imagem anterior ao encontrar novo produto
            $this->data['image_nutritional'] = null;
            $this->form->fill($this->data); 
        } else {
            $this->foundProduct = null;
            Notification::make()->title('EAN não cadastrado')->danger()->send();
            $this->dispatch('reset-scanner');
        }
    }

    public function onFileUploaded()
    {
        // Método auxiliar apenas para garantir state update se necessário
    }

    public function save()
    {
        $state = $this->form->getState();
        
        if ($this->foundProduct && !empty($state['image_nutritional'])) {
            // Em caso de array (multifile), pega o primeiro, senão pega a string direta
            $path = is_array($state['image_nutritional']) 
                ? array_values($state['image_nutritional'])[0] 
                : $state['image_nutritional'];

            $this->foundProduct->update(['image_nutritional' => $path]);
            
            Notification::make()->title('Salvo com sucesso!')->success()->send();
            $this->resetScanner();
        } else {
            Notification::make()->title('Nenhuma imagem capturada')->warning()->send();
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