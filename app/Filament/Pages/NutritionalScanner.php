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

    public function mount(): void { $this->form->fill(); }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('image_nutritional')
                    ->hidden() // Fica oculto pois o upload agora é controlado via processCroppedImage
                    ->image()
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    ->statePath('image_nutritional'),
            ])
            ->statePath('data');
    }

    /**
     * Recebe a imagem do Cropper.js (Base64), salva no storage e atualiza o estado.
     */
    public function processCroppedImage(string $base64Data)
    {
        try {
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $image = substr($base64Data, strpos($base64Data, ',') + 1);
                $image = base64_decode($image);

                $filename = 'crop_' . uniqid() . '.jpg';
                $path = 'uploads/nutritional/' . $filename;

                Storage::disk('public')->put($path, $image);

                // Sincroniza com o estado do formulário para o método save() funcionar
                $this->data['image_nutritional'] = $path;
                
                return true;
            }
        } catch (\Exception $e) {
            Notification::make()->title('Erro ao processar imagem')->danger()->send();
        }
        return false;
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            $this->form->fill(['image_nutritional' => null]); 
        } else {
            $this->foundProduct = null;
            Notification::make()->title('EAN não cadastrado')->danger()->send();
            $this->dispatch('reset-scanner');
        }
    }

    public function save()
    {
        // Pega o estado atual (que foi preenchido pelo processCroppedImage)
        if ($this->foundProduct && !empty($this->data['image_nutritional'])) {
            $this->foundProduct->update([
                'image_nutritional' => $this->data['image_nutritional']
            ]);

            Notification::make()->title('Dados salvos com sucesso!')->success()->send();
            $this->resetScanner();
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