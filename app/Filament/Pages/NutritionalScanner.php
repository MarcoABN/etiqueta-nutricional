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
    protected static ?string $title = ''; // Layout limpo
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
                    // --- OTIMIZAÇÃO DE ESPAÇO (Reduz para ~720p) ---
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1280)
                    ->imageResizeTargetHeight(1280)
                    ->imageResizeUpscale(false)
                    // Ativa o editor para o usuário recortar apenas a tabela
                    ->imageEditor()
                    ->imageEditorMode(2) 
                    // ----------------------------------------------
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) {
                        $cod = $this->foundProduct->codprod ?? 'EAN_' . $this->scannedCode;
                        return "{$cod}_nutri_" . now()->format('Ymd_His') . '.' . $file->getClientOriginalExtension();
                    })
                    ->required()
                    ->extraInputAttributes([
                        'capture' => 'environment', // Abre câmera traseira direto
                        'accept' => 'image/*'
                    ])
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
            // Preenche o formulário com a imagem atual se existir
            $this->form->fill([
                'image_nutritional' => $product->image_nutritional,
            ]);
        } else {
            $this->foundProduct = null;
            Notification::make()
                ->title('Produto não encontrado')
                ->body("EAN: {$code}")
                ->danger()
                ->duration(3000)
                ->send();
            
            $this->dispatch('reset-scanner-error');
        }
    }

    public function save()
    {
        $data = $this->form->getState();

        if ($this->foundProduct && !empty($data['image_nutritional'])) {
            
            // Limpeza de imagem antiga para não acumular lixo no servidor
            $oldImage = $this->foundProduct->image_nutritional;
            if ($oldImage && $oldImage !== $data['image_nutritional']) {
                if (Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
            }

            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()
                ->title('Foto salva com sucesso!')
                ->success()
                ->send();
            
            $this->resetScanner();
        } else {
            Notification::make()
                ->title('Erro ao salvar')
                ->body('Certifique-se de que a foto foi carregada.')
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
        $this->dispatch('reset-scanner'); 
    }
}