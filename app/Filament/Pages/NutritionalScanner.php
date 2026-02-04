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

    public $scannedCode = null;
    public $foundProduct = null;
    public $photo; // Arquivo temporário
    
    // NOVO: Controla o estado da tela pelo servidor (confirm | preview)
    public $viewMode = 'confirm'; 

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
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
            $this->reset('photo');
            
            // Se já tem foto no banco, vai direto pro preview. Se não, pede confirmação.
            if (!empty($product->image_nutritional)) {
                $this->viewMode = 'preview';
            } else {
                $this->viewMode = 'confirm';
            }

        } else {
            $this->foundProduct = null;
            $this->viewMode = 'confirm';
            
            Notification::make()
                ->title('Produto não encontrado')
                ->body("EAN: {$code}")
                ->danger()
                ->duration(2500)
                ->send();
            
            $this->dispatch('reset-scanner-error');
        }
    }

    // HOOK AUTOMÁTICO DO LIVEWIRE
    // Disparado assim que o upload da variável $photo termina
    public function updatedPhoto()
    {
        // Força a mudança para a tela de preview assim que a foto sobe
        $this->viewMode = 'preview';
    }

    public function save()
    {
        if (!$this->foundProduct) return;

        // Se não tiver foto nova E não tiver foto antiga, avisa
        if (!$this->photo && empty($this->foundProduct->image_nutritional)) {
            Notification::make()->title('Nenhuma foto para salvar.')->warning()->send();
            return;
        }

        try {
            // Só faz upload se tiver uma foto NOVA
            if ($this->photo) {
                $cod = $this->foundProduct->codprod ?? 'SEM_COD';
                $cod = preg_replace('/[^A-Za-z0-9\-]/', '', $cod);
                $filename = "{$cod}_nutri_" . time() . '.' . $this->photo->getClientOriginalExtension();

                $path = $this->photo->storeAs('uploads/nutritional', $filename, 'public');

                // Apaga antiga
                $oldImage = $this->foundProduct->image_nutritional;
                if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }

                $this->foundProduct->update(['image_nutritional' => $path]);
            }

            Notification::make()->title('Salvo com sucesso!')->success()->duration(1500)->send();
            $this->resetScanner();

        } catch (\Exception $e) {
            Notification::make()->title('Erro')->body($e->getMessage())->danger()->send();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->photo = null;
        $this->viewMode = 'confirm'; // Reseta estado
        $this->dispatch('reset-scanner'); 
    }
}