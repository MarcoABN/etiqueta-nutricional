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
    protected static ?string $title = 'Scanner de Rótulos';
    protected static string $view = 'filament.pages.nutritional-scanner';

    // Variáveis de Estado
    public $scannedCode = null;
    public $foundProduct = null;
    public ?array $data = []; // Para armazenar o form

    // Monta o formulário de upload APENAS se tiver produto
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Foto da Tabela Nutricional')
                    ->description('Capture a foto focando bem nos números.')
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

    // Função chamada pelo Javascript quando lê o código
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
            Notification::make()->title('Produto Encontrado: ' . $product->product_name)->success()->send();
        } else {
            $this->foundProduct = null;
            Notification::make()->title('Produto não encontrado!')->warning()->send();
        }
    }

    // Salva a imagem no produto
    public function save()
    {
        $data = $this->form->getState();

        if ($this->foundProduct) {
            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()->title('Imagem salva com sucesso!')->success()->send();
            
            // Reseta para o próximo
            $this->resetScanner();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->form->fill();
        // Emite evento para o JS reiniciar a câmera se necessário
        $this->dispatch('reset-scanner'); 
    }
}