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
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1280)
                    ->imageResizeTargetHeight(1280)
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    // Importante: deleta o arquivo do servidor se o usuário remover no UI
                    ->extraInputAttributes(['capture' => 'environment'])
                    ->live() 
                    // Força o Livewire a emitir um evento para o JS quando o estado mudar
                    ->afterStateUpdated(fn ($state) => $this->dispatch('file-uploaded-callback'))
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
            
            // RESETAMOS o formulário para garantir que o FilePond não se confunda
            // com a imagem antiga na hora de processar a nova
            $this->form->fill(['image_nutritional' => null]); 
            
            // Se você quiser mostrar a imagem antiga, faremos isso via HTML puro no Blade
            // para não "sujar" o componente de upload
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

            Notification::make()->title('Salvo com sucesso!')->success()->send();
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