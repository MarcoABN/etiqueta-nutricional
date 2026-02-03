<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage; // Necessário para apagar a foto antiga
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
                    // --- OTIMIZAÇÃO DE PERFORMANCE (720p) ---
                    // Reduz drasticamente o tempo de processamento da CPU do servidor
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1280)  // Largura Máxima HD
                    ->imageResizeTargetHeight(1280) // Altura Máxima HD (cobre fotos verticais)
                    ->imageResizeUpscale(false)
                    // ----------------------------------------
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) {
                        $cod = $this->foundProduct->codprod ?? 'SEM_COD';
                        // Timestamp evita que o navegador mostre a foto antiga do cache
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
            // Carrega a imagem atual no formulário para o usuário ver
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
            // Valida o formulário. Se o upload não terminou, o Filament lança exceção aqui.
            $data = $this->form->getState();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Aguarde o envio...')
                ->body('A imagem ainda está sendo enviada. Aguarde o ícone de carregamento sumir.')
                ->warning()
                ->send();
            return;
        }

        if ($this->foundProduct && !empty($data['image_nutritional'])) {
            
            // --- LIMPEZA AUTOMÁTICA (SOBRESCREVER) ---
            $oldImage = $this->foundProduct->image_nutritional;
            
            // Se já existia uma foto antes, apagamos do disco para liberar espaço
            // (Comparamos se o caminho é diferente para garantir)
            if ($oldImage && $oldImage !== $data['image_nutritional']) {
                if (Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
            // ------------------------------------------

            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()
                ->title('Salvo!')
                ->success()
                ->duration(1500)
                ->send();
            
            // Reinicia o fluxo imediatamente
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
        // Avisa o front-end para limpar a tela e ligar a câmera de novo
        $this->dispatch('reset-scanner'); 
    }
}