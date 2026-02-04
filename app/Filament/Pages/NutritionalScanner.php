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
                    ->live() // <--- O PULO DO GATO: Sincroniza assim que o upload termina
                    // Otimização HD (720p) - Rápido e Legível
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1280)
                    ->imageResizeTargetHeight(1280)
                    ->imageResizeUpscale(false)
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    // Nomeia com timestamp para evitar cache e conflito
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) {
                        $cod = $this->foundProduct->codprod ?? 'SEM_COD';
                        return "{$cod}_nutri_" . time() . '.' . $file->getClientOriginalExtension();
                    })
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
        try {
            // Agora com ->live(), o getState() já deve ter o valor correto
            $data = $this->form->getState();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Aguarde...')
                ->body('Finalizando processamento da imagem.')
                ->warning()
                ->send();
            return;
        }

        if ($this->foundProduct && !empty($data['image_nutritional'])) {
            
            // --- Lógica de Sobrescrita (Limpeza) ---
            $oldImage = $this->foundProduct->image_nutritional;
            
            // Se a imagem nova for diferente da antiga (o nome sempre muda pelo time()), apaga a velha
            if ($oldImage && $oldImage !== $data['image_nutritional']) {
                if (Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
            }

            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()
                ->title('Salvo!')
                ->success()
                ->duration(1500)
                ->send();
            
            $this->resetScanner();
        } else {
            Notification::make()->title('Erro ao salvar')->danger()->send();
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