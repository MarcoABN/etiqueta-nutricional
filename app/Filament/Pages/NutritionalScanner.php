<?php

namespace App\Filament\Pages;

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
                    // Redimensionamento nativo para otimizar (Full HD)
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1080)
                    ->imageResizeTargetHeight(1920)
                    ->imageResizeUpscale(false)
                    ->directory('uploads/nutritional')
                    ->required()
                    // ID CSS para o Javascript encontrar este input
                    ->extraAttributes(['id' => 'nutritional-upload-component'])
                    // Força comportamento de câmera no mobile
                    ->extraInputAttributes([
                        'capture' => 'environment',
                        'accept' => 'image/*'
                    ])
                    ->panelLayout('integrated')
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
            // Se já tiver foto, carrega para visualização
            $this->form->fill([
                'image_nutritional' => $product->image_nutritional,
            ]);
        } else {
            $this->foundProduct = null;
            Notification::make()
                ->title('EAN não encontrado')
                ->body($code)
                ->danger()
                ->duration(3000)
                ->send();
            
            // Reabre scanner após erro
            $this->dispatch('reset-scanner-error');
        }
    }

    public function save()
    {
        $data = $this->form->getState();

        // Validação simples
        if ($this->foundProduct && !empty($data['image_nutritional'])) {
            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()->title('Salvo com sucesso!')->success()->send();
            $this->resetScanner();
        } else {
             Notification::make()->title('É necessário tirar a foto!')->warning()->send();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->form->fill();
        $this->dispatch('reset-scanner'); 
    }
}