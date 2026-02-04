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
                    ->live()
                    // Otimização HD (720p/1080p) - Rápido e Legível
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1280)
                    ->imageResizeTargetHeight(1280)
                    ->imageResizeUpscale(false)
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    // Nomeia com timestamp para evitar cache e conflito
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) {
                        $cod = $this->foundProduct->codprod ?? 'SEM_COD';
                        // Limpa caracteres especiais do código do produto para evitar erros no sistema de arquivos
                        $cod = preg_replace('/[^A-Za-z0-9\-]/', '', $cod);
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
            // Preenche o formulário com a imagem existente, se houver
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
        // CORREÇÃO: Acessa diretamente os dados sincronizados pelo Livewire ($this->data)
        // em vez de forçar uma validação completa com getState(), que causa o loop infinito
        // quando o upload ainda está sendo processado internamente.
        
        $rawImage = $this->data['image_nutritional'] ?? null;
        $imagePath = null;

        // O FileUpload pode retornar um array (uuid => path) ou string direta
        if (is_array($rawImage)) {
            // Pega o primeiro valor do array (o caminho do arquivo)
            $imagePath = array_values($rawImage)[0] ?? null;
        } elseif (is_string($rawImage)) {
            $imagePath = $rawImage;
        }

        // Validação Manual: Se não tiver produto ou não tiver caminho de imagem válido
        if (!$this->foundProduct) {
            Notification::make()->title('Nenhum produto selecionado')->danger()->send();
            return;
        }

        if (empty($imagePath)) {
            Notification::make()
                ->title('Aguarde...')
                ->body('A imagem ainda está sendo enviada ou processada.')
                ->warning()
                ->send();
            return;
        }

        // Processo de Salvamento
        try {
            $oldImage = $this->foundProduct->image_nutritional;
            
            // Se a imagem mudou, deleta a antiga do disco para economizar espaço
            if ($oldImage && $oldImage !== $imagePath) {
                if (Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
            }

            $this->foundProduct->update([
                'image_nutritional' => $imagePath
            ]);

            Notification::make()
                ->title('Salvo com sucesso!')
                ->success()
                ->duration(1500)
                ->send();
            
            $this->resetScanner();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erro ao salvar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->data = []; 
        $this->form->fill(); // Limpa o formulário visualmente
        $this->dispatch('reset-scanner'); 
    }
}