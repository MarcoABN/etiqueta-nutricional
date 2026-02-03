<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\On;

class NutritionalScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Scanner';
    protected static ?string $title = ''; // Título vazio para não renderizar cabeçalho padrão
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
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1080)
                    ->imageResizeTargetHeight(1920)
                    ->imageResizeUpscale(false)
                    ->directory('uploads/nutritional')
                    ->required()
                    // Atributos essenciais para abrir a câmera nativa direto
                    ->extraInputAttributes([
                        'capture' => 'environment',
                        'accept' => 'image/*'
                    ])
                    // Removemos layouts complexos, controlaremos via CSS da view
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            // Preenche imagem existente caso queira ver/substituir
            $this->form->fill([
                'image_nutritional' => $product->image_nutritional,
            ]);
        } else {
            // Se não achar, apenas avisa e reinicia scan
            Notification::make()
                ->title('Produto não encontrado')
                ->body("EAN: {$code}")
                ->warning()
                ->duration(2000)
                ->send();
            
            $this->dispatch('resume-scanner');
        }
    }

    public function save()
    {
        $data = $this->form->getState();

        if ($this->foundProduct && !empty($data['image_nutritional'])) {
            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()->title('Salvo com sucesso!')->success()->send();
            $this->resetScanner();
        } else {
            Notification::make()->title('Capture a foto primeiro')->warning()->send();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->form->fill();
        $this->dispatch('start-scanner'); 
    }
}