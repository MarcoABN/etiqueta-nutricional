<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class NutritionalScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Coletor Nutricional';
    
    // Deixe vazio para remover o cabeçalho padrão e ganhar espaço no mobile
    protected static ?string $title = ''; 
    
    protected static string $view = 'filament.pages.nutritional-scanner';

    // --- ESTAS VARIÁVEIS SÃO OBRIGATÓRIAS ---
    public $scannedCode = null;
    public $foundProduct = null; // <--- O ERRO OCORRE SE ESTA LINHA FALTAR
    public ?array $data = []; 
    // ----------------------------------------

    public function mount(): void
    {
        // Garante que o formulário inicie vazio
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Foto da Tabela Nutricional')
                    ->schema([
                        FileUpload::make('image_nutritional')
                            ->label('Câmera / Arquivo')
                            ->image()
                            ->imageEditor()
                            ->imageEditorMode(2)
                            ->directory('uploads/nutritional')
                            ->required()
                            ->columnSpanFull(),
                    ])
            ])
            ->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        
        // Busca o produto pelo EAN
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            
            // Carrega imagem existente se houver
            $this->form->fill([
                'image_nutritional' => $product->image_nutritional,
            ]);
            
            // Toca um som ou notifica (opcional)
            // Notification::make()->title('Encontrado: ' . $product->product_name)->success()->send();
        } else {
            $this->foundProduct = null;
            Notification::make()->title('EAN não encontrado: ' . $code)->warning()->send();
        }
    }

    public function save()
    {
        $data = $this->form->getState();

        if ($this->foundProduct) {
            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()->title('Foto salva!')->success()->send();
            
            $this->resetScanner();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->form->fill();
        
        // Avisa o Front-end para religar a câmera
        $this->dispatch('reset-scanner'); 
    }
}