<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

class NutritionalScanner extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Coletor Nutricional';
    protected static ?string $title = '';
    protected static string $view = 'filament.pages.nutritional-scanner';

    // Propriedades do Fluxo
    public $scannedCode = null;
    public $foundProduct = null;
    
    // O arquivo temporário da foto
    public $photo; 

    // Controle de Etapas: 'scan' | 'confirm' | 'preview'
    public $step = 'scan'; 

    public function mount(): void
    {
        // Se recarregar a página, garante estado limpo
        $this->step = 'scan';
    }

    public function form(Form $form): Form
    {
        return $form->schema([])->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            $this->reset('photo'); // Limpa foto anterior da memória

            // Se o produto JÁ TEM foto salva, vai para o preview.
            // Se não tem, vai para a tela de confirmação/câmera.
            if (!empty($product->image_nutritional)) {
                $this->step = 'preview';
            } else {
                $this->step = 'confirm';
            }

        } else {
            $this->foundProduct = null;
            $this->step = 'scan';
            
            Notification::make()
                ->title('Produto não encontrado')
                ->body("EAN: {$code}")
                ->danger()
                ->duration(3000)
                ->send();
            
            $this->dispatch('reset-scanner-error');
        }
    }

    // GATILHO IMPORTANTE:
    // O Livewire chama isso automaticamente assim que o upload termina.
    public function updatedPhoto()
    {
        // Força a mudança de etapa para 'preview'
        $this->step = 'preview';
    }

    public function save()
    {
        // 1. Validações de Segurança
        if (!$this->foundProduct) return;

        // Se não tem foto nova E não tem foto velha, erro.
        if (!$this->photo && empty($this->foundProduct->image_nutritional)) {
            Notification::make()->title('Erro: Nenhuma imagem detectada.')->warning()->send();
            return;
        }

        try {
            // 2. Processamento do Upload (apenas se houver foto nova)
            if ($this->photo) {
                
                $cod = $this->foundProduct->codprod ?? 'SEM_COD';
                $cod = preg_replace('/[^A-Za-z0-9\-]/', '', $cod); // Sanitiza nome
                
                $filename = "{$cod}_nutri_" . time() . '.' . $this->photo->getClientOriginalExtension();

                // Salva no disco
                $path = $this->photo->storeAs('uploads/nutritional', $filename, 'public');

                // Apaga imagem antiga para não lotar o servidor
                $oldImage = $this->foundProduct->image_nutritional;
                if ($oldImage && $oldImage !== $path) {
                     if (Storage::disk('public')->exists($oldImage)) {
                        Storage::disk('public')->delete($oldImage);
                    }
                }

                // Atualiza Banco
                $this->foundProduct->update([
                    'image_nutritional' => $path
                ]);
            }

            Notification::make()->title('Imagem Salva!')->success()->duration(1500)->send();
            
            // Reinicia o processo
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
        $this->photo = null;
        $this->step = 'scan'; // Volta para o scanner
        $this->dispatch('reset-scanner'); 
    }
}