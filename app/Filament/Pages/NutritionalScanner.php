<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
                    ->imageResizeTargetWidth(1920)
                    ->imageResizeTargetHeight(1080)
                    ->imageResizeUpscale(false)
                    ->directory('uploads/nutritional')
                    ->disk('public') // Força disco público
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) {
                        $cod = $this->foundProduct->codprod ?? 'SEM_COD';
                        return "{$cod}_nutri_" . time() . '.' . $file->getClientOriginalExtension();
                    })
                    ->required() // Validação obrigatória
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
            // Tenta validar o formulário. Se a imagem ainda estiver subindo, falha aqui.
            $data = $this->form->getState();
        } catch (\Exception $e) {
            // Se der erro (ex: upload incompleto), avisa o usuário
            Notification::make()
                ->title('Aguarde o fim do upload')
                ->body('A imagem está sendo enviada ao servidor. Tente novamente em alguns segundos.')
                ->warning()
                ->send();
            return;
        }

        if ($this->foundProduct && !empty($data['image_nutritional'])) {
            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()
                ->title('Salvo com sucesso!')
                ->success()
                ->duration(1500) // Rápido para não travar o fluxo
                ->send();
            
            // Reinicia o processo imediatamente
            $this->resetScanner();
        } else {
            Notification::make()->title('Erro ao salvar')->danger()->send();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        // Limpa o formulário e a variável temporária de imagem
        $this->data = []; 
        $this->form->fill();
        
        // Dispara evento para o Front limpar o preview e reiniciar câmera
        $this->dispatch('reset-scanner'); 
    }
}