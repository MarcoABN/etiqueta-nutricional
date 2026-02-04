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
                    // Redimensionamento para 720p (HD) - Redução de custo de storage
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1280)
                    ->imageResizeTargetHeight(1280)
                    // Desativamos o editor avançado para evitar que o processo pare no "aguardando crop"
                    // Mas mantemos a compressão automática
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) {
                        $cod = $this->foundProduct->codprod ?? 'EAN_' . $this->scannedCode;
                        return "{$cod}_" . now()->format('Ymd_His') . '.' . $file->getClientOriginalExtension();
                    })
                    ->extraInputAttributes(['capture' => 'environment'])
                    ->required()
                    ->live() // Garante que o Livewire saiba imediatamente quando o upload termina
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
            $this->form->fill(['image_nutritional' => $product->image_nutritional]);
        } else {
            $this->foundProduct = null;
            Notification::make()->title('Produto não encontrado')->danger()->send();
            $this->dispatch('reset-scanner-error');
        }
    }

    public function save()
    {
        $state = $this->form->getState();

        if ($this->foundProduct && !empty($state['image_nutritional'])) {
            $oldImage = $this->foundProduct->image_nutritional;
            $this->foundProduct->update(['image_nutritional' => $state['image_nutritional']]);

            if ($oldImage && $oldImage !== $state['image_nutritional']) {
                Storage::disk('public')->delete($oldImage);
            }

            Notification::make()->title('Dados salvos!')->success()->send();
            $this->resetScanner();
        } else {
            Notification::make()->title('Erro: Selecione a foto primeiro')->warning()->send();
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