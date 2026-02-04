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
use Livewire\Attributes\Validate; // Importante para validação

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

    // Validação: Máximo 12MB, tipos de imagem aceitos
    #[Validate('image|max:12288')] 
    public $photo; 

    public $step = 'scan'; 

    public function mount(): void
    {
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
            $this->reset('photo');

            if (!empty($product->image_nutritional)) {
                $this->step = 'preview';
            } else {
                $this->step = 'confirm';
            }
        } else {
            $this->foundProduct = null;
            $this->step = 'scan';
            Notification::make()->title('Produto não encontrado')->body($code)->danger()->send();
            $this->dispatch('reset-scanner-error');
        }
    }

    // Este método roda assim que o upload termina (com sucesso ou falha)
    public function updatedPhoto()
    {
        // Se houver erros de validação (ex: arquivo grande), o Livewire preenche a ErrorBag
        $this->validateOnly('photo');

        // Se passou da validação, muda a etapa
        if ($this->photo) {
            $this->step = 'preview';
        }
    }

    public function save()
    {
        if (!$this->foundProduct) return;

        // Valida novamente antes de salvar
        $this->validate([
            'photo' => 'nullable|image|max:12288',
        ]);

        if (!$this->photo && empty($this->foundProduct->image_nutritional)) {
            Notification::make()->title('Nenhuma imagem detectada.')->warning()->send();
            return;
        }

        try {
            if ($this->photo) {
                $cod = preg_replace('/[^A-Za-z0-9\-]/', '', $this->foundProduct->codprod ?? 'SEM_COD');
                $filename = "{$cod}_nutri_" . time() . '.' . $this->photo->getClientOriginalExtension();
                
                $path = $this->photo->storeAs('uploads/nutritional', $filename, 'public');

                $oldImage = $this->foundProduct->image_nutritional;
                if ($oldImage && $oldImage !== $path && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }

                $this->foundProduct->update(['image_nutritional' => $path]);
            }

            Notification::make()->title('Salvo com sucesso!')->success()->send();
            $this->resetScanner();

        } catch (\Exception $e) {
            Notification::make()->title('Erro ao salvar')->body($e->getMessage())->danger()->send();
        }
    }

    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->photo = null;
        $this->step = 'scan'; 
        $this->dispatch('reset-scanner'); 
    }
}