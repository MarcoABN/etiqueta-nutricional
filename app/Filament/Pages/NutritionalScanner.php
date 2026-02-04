<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class NutritionalScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Coletor Nutricional';
    protected static ?string $title = 'Coletor Nutricional';
    protected static string $view = 'filament.pages.nutritional-scanner';

    public ?string $scannedCode = null;
    public ?Product $foundProduct = null;

    /**
     * Estado do form (FileUpload vai escrever aqui)
     */
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
                    ->live() // garante que sincroniza assim que o upload termina [file:2]
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1280)
                    ->imageResizeTargetHeight(1280)
                    ->imageResizeUpscale(false)
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) {
                        $cod = $this->foundProduct?->codprod ?? 'SEM_COD';
                        return "{$cod}_nutri_" . time() . '.' . $file->getClientOriginalExtension();
                    })
                    ->required()
                    ->extraAttributes(['id' => 'nutritional-upload-component'])
                    ->extraInputAttributes([
                        'capture' => 'environment',
                        'accept' => 'image/*',
                    ])
                    ->panelLayout('integrated')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function handleBarcodeScan(string $code): void
    {
        $this->scannedCode = $code;

        $product = Product::where('barcode', $code)->first();

        if (! $product) {
            $this->foundProduct = null;

            Notification::make()
                ->title('Produto não encontrado')
                ->body("EAN: {$code}")
                ->danger()
                ->duration(2500)
                ->send();

            $this->dispatch('reset-scanner-error');
            return;
        }

        $this->foundProduct = $product;

        // Preenche com a imagem atual (se existir)
        $this->form->fill([
            'image_nutritional' => $product->image_nutritional,
        ]);
    }

    public function save(): void
    {
        // 1) Precisa ter produto selecionado
        if (! $this->foundProduct) {
            Notification::make()
                ->title('Nenhum produto selecionado')
                ->body('Leia um código de barras antes de salvar a foto.')
                ->warning()
                ->duration(2500)
                ->send();

            return;
        }

        // 2) Pega o estado atual do form (sem try/catch “silencioso”)
        $data = $this->form->getState();

        // 3) Se a imagem ainda não chegou no state, é porque o upload não terminou
        if (empty($data['image_nutritional'])) {
            Notification::make()
                ->title('Aguarde...')
                ->body('Envio da imagem em andamento. Tente salvar novamente quando o upload terminar.')
                ->warning()
                ->duration(2500)
                ->send();

            return;
        }

        $newImage = $data['image_nutritional'];
        $oldImage = $this->foundProduct->image_nutritional;

        // 4) Atualiza o produto primeiro (garante persistência)
        $this->foundProduct->update([
            'image_nutritional' => $newImage,
        ]);

        // 5) Remove a antiga somente se ela existir e for diferente da nova
        if ($oldImage && $oldImage !== $newImage) {
            if (Storage::disk('public')->exists($oldImage)) {
                Storage::disk('public')->delete($oldImage);
            }
        }

        Notification::make()
            ->title('Salvo!')
            ->success()
            ->duration(1500)
            ->send();

        $this->resetScanner();
    }

    public function resetScanner(): void
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->data = [];

        $this->form->fill();

        $this->dispatch('reset-scanner');
    }
}
