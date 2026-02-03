<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

class NutritionalScanner extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Scanner App';
    protected static ?string $title = 'Coletor de Rótulos';
    protected static string $view = 'filament.pages.nutritional-scanner';

    // Estados da Tela
    public $viewState = 'scan'; // 'scan' (código) ou 'capture' (foto)
    public $scannedProduct = null;
    public $scannedCode = null;
    
    // Imagem capturada (Base64)
    public $capturedImage = null;

    public function mount()
    {
        $this->resetScanner();
    }

    // 1. Recebe o código do JS
    public function handleBarcodeScan($code)
    {
        if ($this->viewState !== 'scan') return;

        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->scannedProduct = $product;
            $this->viewState = 'capture'; // Muda a tela para o modo FOTO
            
            // Avisa o front para ligar a câmera de alta resolução
            $this->dispatch('start-photo-camera'); 
            
            Notification::make()->title("Produto: {$product->product_name}")->success()->send();
        } else {
            Notification::make()->title("EAN {$code} não encontrado!")->danger()->send();
            $this->dispatch('resume-scanner');
        }
    }

    // 2. Recebe a foto do JS (Base64) e salva
    public function savePhoto($base64Image)
    {
        if (!$this->scannedProduct) return;

        // Decodifica a imagem Base64
        // O formato vem como "data:image/jpeg;base64,....."
        $imageParts = explode(";base64,", $base64Image);
        $imageTypeAux = explode("image/", $imageParts[0]);
        $imageType = $imageTypeAux[1] ?? 'jpeg';
        $imageBase64 = base64_decode($imageParts[1]);

        // Gera nome único
        $fileName = 'nutritional_' . $this->scannedProduct->id . '_' . time() . '.' . $imageType;
        $path = 'uploads/nutritional/' . $fileName;

        // Salva no disco (Storage Public)
        Storage::disk('public')->put($path, $imageBase64);

        // Atualiza o produto
        $this->scannedProduct->update([
            'image_nutritional' => $path
        ]);

        Notification::make()->title('Foto salva com sucesso!')->success()->send();
        
        // Reinicia o processo
        $this->resetScanner();
    }

    #[On('reset-action')] 
    public function resetScanner()
    {
        $this->viewState = 'scan';
        $this->scannedProduct = null;
        $this->scannedCode = null;
        $this->capturedImage = null;
        
        $this->dispatch('start-scanner'); // Volta para o modo scanner de código
    }
}