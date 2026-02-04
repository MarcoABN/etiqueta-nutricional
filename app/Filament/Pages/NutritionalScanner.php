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
                // Este componente fica invisível e serve apenas para validar/salvar o caminho no banco
                FileUpload::make('image_nutritional')
                    ->hiddenLabel()
                    ->image()
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    ->extraAttributes(['class' => '!hidden']) // Garante ocultação visual
                    ->statePath('image_nutritional'),
            ])
            ->statePath('data');
    }

    /**
     * Processa a imagem recortada vinda do JavaScript (Base64)
     */
    public function processCroppedImage(string $base64Data)
    {
        try {
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $image = substr($base64Data, strpos($base64Data, ',') + 1);
                $image = base64_decode($image);

                if ($image === false) {
                    throw new \Exception('Falha ao decodificar imagem');
                }

                $filename = 'crop_' . time() . '_' . uniqid() . '.jpg';
                $path = 'uploads/nutritional/' . $filename;

                Storage::disk('public')->put($path, $image);

                // Vincula o caminho do arquivo ao estado do formulário
                $this->data['image_nutritional'] = $path;
                
                return true;
            }
        } catch (\Exception $e) {
            Notification::make()->title('Erro ao processar imagem: ' . $e->getMessage())->danger()->send();
        }
        return false;
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            // Limpa imagem anterior se houver
            $this->data['image_nutritional'] = null;
        } else {
            // Produto não encontrado
            $this->foundProduct = null;
            Notification::make()
                ->title('EAN não cadastrado')
                ->body("O código $code não foi encontrado.")
                ->danger()
                ->duration(3000)
                ->send();
            
            // Dispara evento para o frontend reiniciar a câmera
            $this->dispatch('reset-scanner');
        }
    }

    public function save()
    {
        if ($this->foundProduct && !empty($this->data['image_nutritional'])) {
            $this->foundProduct->update([
                'image_nutritional' => $this->data['image_nutritional']
            ]);

            Notification::make()->title('Salvo com sucesso!')->success()->send();
            $this->resetScanner();
        } else {
            Notification::make()->title('Erro: Imagem não capturada')->warning()->send();
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