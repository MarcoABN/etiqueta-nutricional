<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile; // Importante

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
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1920)
                    ->imageResizeTargetHeight(1080)
                    ->imageResizeUpscale(false)
                    ->directory('uploads/nutritional')
                    // --- RENOMEAÇÃO PERSONALIZADA ---
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) {
                        $cod = $this->foundProduct->codprod ?? 'SEM_COD';
                        // Adicionamos timestamp para evitar cache de navegador se atualizar a foto
                        return "{$cod}_nutri_" . time() . '.' . $file->getClientOriginalExtension();
                    })
                    // -------------------------------
                    ->required()
                    ->extraAttributes(['id' => 'nutritional-upload-component'])
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
            $this->form->fill([
                'image_nutritional' => $product->image_nutritional,
            ]);
        } else {
            $this->foundProduct = null;
            Notification::make()
                ->title('Produto não encontrado')
                ->body("EAN: {$code}")
                ->danger()
                ->duration(2500)
                ->send();
            
            $this->dispatch('reset-scanner-error');
        }
    }

    public function save()
    {
        $data = $this->form->getState();

        if ($this->foundProduct && !empty($data['image_nutritional'])) {
            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()
                ->title('Imagem salva com sucesso!')
                ->success()
                ->duration(2000)
                ->send();
            
            $this->resetScanner();
        } else {
            Notification::make()
                ->title('Capture a foto primeiro')
                ->warning()
                ->duration(2000)
                ->send();
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