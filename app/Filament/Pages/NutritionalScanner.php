<?php

namespace App\Filament\Pages;

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
                    
                    // --- NOVA CONFIGURAÇÃO DE CROP (RECORTAR) ---
                    ->imageEditor() // Ativa o editor (Cropper.js)
                    ->imageEditorMode(2) // 2 = Modal (Ideal para mobile)
                    ->imageCropAspectRatio(null) // Null = Livre (Sem proporção fixa)
                    
                    // --- OTIMIZAÇÃO PÓS-CROP ---
                    // Definimos um limite alto apenas para não salvar arquivos gigantescos (20MB+),
                    // mas mantemos qualidade suficiente para o OCR ler letras pequenas.
                    ->imageResizeTargetWidth('2000') 
                    ->imageResizeTargetHeight('2000')
                    
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    
                    // Mantém a captura de câmera, mas permite acesso à galeria para edição
                    ->extraInputAttributes([
                        'capture' => 'environment',
                        'accept' => 'image/*'
                    ])
                    ->live() 
                    ->afterStateUpdated(fn () => $this->dispatch('file-uploaded-callback'))
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
            // Limpa o campo de imagem para nova captura
            $this->form->fill(['image_nutritional' => null]); 
        } else {
            $this->foundProduct = null;
            Notification::make()
                ->title('EAN não cadastrado')
                ->danger()
                ->send();
            
            // Reinicia o scanner após erro
            $this->dispatch('reset-scanner');
        }
    }

    public function save()
    {
        $state = $this->form->getState();
        
        if ($this->foundProduct && !empty($state['image_nutritional'])) {
            // Salva o caminho da imagem recortada no produto
            $this->foundProduct->update([
                'image_nutritional' => $state['image_nutritional']
            ]);

            Notification::make()
                ->title('Imagem salva e enviada para IA!')
                ->success()
                ->send();

            $this->resetScanner();
        } else {
            Notification::make()
                ->title('Erro: Nenhuma imagem capturada.')
                ->warning()
                ->send();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->data = [];
        $this->form->fill();
        // Emite evento para o Javascript reiniciar a câmera
        $this->dispatch('reset-scanner'); 
    }
}