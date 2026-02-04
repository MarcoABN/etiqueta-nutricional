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
use Illuminate\Support\Str;

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
                // Componente "fantasma" apenas para estrutura de dados.
                // A interface real é controlada via AlpineJS e processCroppedImage
                FileUpload::make('image_nutritional')
                    ->hiddenLabel()
                    ->image()
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    ->extraAttributes(['class' => '!hidden']) 
                    ->statePath('image_nutritional'),
            ])
            ->statePath('data');
    }

    public function processCroppedImage(string $base64Data)
    {
        try {
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $image = substr($base64Data, strpos($base64Data, ',') + 1);
                $image = base64_decode($image);

                if ($image === false) {
                    throw new \Exception('Falha na decodificação da imagem.');
                }

                $filename = 'crop_' . time() . '_' . Str::random(10) . '.jpg';
                $path = 'uploads/nutritional/' . $filename;

                Storage::disk('public')->put($path, $image);

                $this->data['image_nutritional'] = $path;
                
                return true;
            }
        } catch (\Exception $e) {
            Notification::make()->title('Erro ao salvar imagem')->body($e->getMessage())->danger()->send();
        }
        return false;
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            $this->data['image_nutritional'] = null; // Reseta imagem anterior
        } else {
            $this->foundProduct = null;
            Notification::make()
                ->title('EAN não encontrado')
                ->body("Código: $code")
                ->warning()
                ->seconds(3)
                ->send();
            
            $this->dispatch('reset-scanner-ui');
        }
    }

    public function save()
    {
        if ($this->foundProduct && !empty($this->data['image_nutritional'])) {
            $this->foundProduct->update([
                'image_nutritional' => $this->data['image_nutritional']
            ]);

            Notification::make()->title('Produto atualizado!')->success()->send();
            $this->resetScanner();
        } else {
            Notification::make()->title('Tire uma foto antes de salvar.')->warning()->send();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->data = [];
        $this->form->fill();
        $this->dispatch('reset-scanner-ui'); 
    }
}