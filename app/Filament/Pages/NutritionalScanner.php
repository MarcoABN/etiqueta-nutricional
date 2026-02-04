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
    protected static ?string $title = ''; // Remove título padrão para ganhar espaço
    protected static string $view = 'filament.pages.nutritional-scanner';

    // Variáveis Públicas (Essenciais para o Livewire)
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
                Section::make('Captura')
                    ->heading('Foto da Tabela Nutricional')
                    ->schema([
                        FileUpload::make('image_nutritional')
                            ->hiddenLabel() // Esconde o label padrão para usarmos o nosso customizado no Blade
                            ->image()
                            ->imageEditor() // Permite recortar a foto após tirar
                            ->imageEditorMode(2)
                            ->directory('uploads/nutritional')
                            ->required()
                            // --- O PULO DO GATO ---
                            // Isso força o celular a abrir a câmera traseira direto, sem perguntar "Galeria ou Câmera?"
                            ->extraInputAttributes([
                                'capture' => 'environment', 
                                'accept' => 'image/*'
                            ])
                            ->columnSpanFull(),
                    ])
            ])
            ->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            // Carrega foto existente se houver, para o usuário ver/substituir
            $this->form->fill([
                'image_nutritional' => $product->image_nutritional,
            ]);
            // Toca um som de sucesso no frontend (via dispatch event se necessário)
        } else {
            // Zera o produto e avisa
            $this->foundProduct = null;
            Notification::make()
                ->title('EAN não cadastrado')
                ->body("Código: {$code}")
                ->danger()
                ->send();
        }
    }

    public function save()
    {
        $data = $this->form->getState();

        if ($this->foundProduct) {
            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()->title('Foto salva com sucesso!')->success()->send();
            
            // Reinicia o fluxo para o próximo produto
            $this->resetScanner();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->form->fill();
        
        // Avisa o front para religar a câmera de leitura
        $this->dispatch('reset-scanner'); 
    }
}