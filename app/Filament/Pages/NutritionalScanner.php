<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;

class NutritionalScanner extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Scanner App';
    protected static ?string $title = 'Coletor de Rótulos';
    protected static string $view = 'filament.pages.nutritional-scanner';

    public $viewState = 'scan'; // 'scan' | 'capture'
    public $scannedProduct = null;
    public $scannedCode = null;

    public function mount()
    {
        $this->resetScanner();
    }

    public function handleBarcodeScan($code)
    {
        if ($this->viewState !== 'scan') return;

        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->scannedProduct = $product;
            $this->viewState = 'capture'; 
            $this->dispatch('start-photo-camera'); 
            
            // Som de sucesso (opcional no front) e notificação curta
            Notification::make()->title("Encontrado: {$product->product_name}")->success()->duration(2000)->send();
        } else {
            Notification::make()->title("EAN {$code} não cadastrado!")->danger()->duration(3000)->send();
            $this->dispatch('resume-scanner');
        }
    }

    public function savePhoto($base64Image)
    {
        if (!$this->scannedProduct) return;

        // Processa a imagem Base64
        $imageParts = explode(";base64,", $base64Image);
        $imageTypeAux = explode("image/", $imageParts[0]);
        $imageType = $imageTypeAux[1] ?? 'jpeg';
        $imageBase64 = base64_decode($imageParts[1]);

        $fileName = 'nutritional_' . $this->scannedProduct->id . '_' . time() . '.' . $imageType;
        $path = 'uploads/nutritional/' . $fileName;

        Storage::disk('public')->put($path, $imageBase64);

        $this->scannedProduct->update([
            'image_nutritional' => $path
        ]);

        Notification::make()->title('Foto Salva!')->success()->duration(1500)->send();
        
        // REINÍCIO AUTOMÁTICO IMEDIATO
        $this->resetScanner();
    }

    #[On('reset-action')] 
    public function resetScanner()
    {
        $this->viewState = 'scan';
        $this->scannedProduct = null;
        $this->scannedCode = null;
        $this->dispatch('start-scanner'); 
    }
}