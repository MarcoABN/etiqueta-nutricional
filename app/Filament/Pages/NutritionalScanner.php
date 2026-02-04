<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads; // <--- Importante: Sistema nativo de upload

class NutritionalScanner extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads; // <--- Habilita uploads diretos

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Coletor Nutricional';
    protected static ?string $title = '';
    protected static string $view = 'filament.pages.nutritional-scanner';

    public $scannedCode = null;
    public $foundProduct = null;
    
    // Variável dedicada para o upload da foto (substitui o form schema complexo)
    public $photo; 

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        // Removemos o FileUpload daqui pois ele estava causando conflito oculto.
        // O formulário fica vazio ou com outros campos se houver.
        return $form
            ->schema([])
            ->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            // Limpa foto anterior da memória se houver
            $this->reset('photo');
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

    // Método chamado automaticamente quando o upload termina (opcional, para debug ou UX)
    public function updatedPhoto()
    {
        $this->dispatch('photo-uploaded');
    }

    public function save()
    {
        if (!$this->foundProduct) {
            Notification::make()->title('Nenhum produto selecionado')->danger()->send();
            return;
        }

        // Validação direta na propriedade $photo
        if (!$this->photo) {
            Notification::make()
                ->title('Nenhuma foto capturada')
                ->body('Por favor, tire a foto novamente.')
                ->warning()
                ->send();
            return;
        }

        try {
            // Definição do nome do arquivo
            $cod = $this->foundProduct->codprod ?? 'SEM_COD';
            $cod = preg_replace('/[^A-Za-z0-9\-]/', '', $cod);
            $filename = "{$cod}_nutri_" . time() . '.' . $this->photo->getClientOriginalExtension();

            // Salva no disco 'public' dentro de 'uploads/nutritional'
            $path = $this->photo->storeAs('uploads/nutritional', $filename, 'public');

            // Lógica de limpar imagem antiga
            $oldImage = $this->foundProduct->image_nutritional;
            if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                Storage::disk('public')->delete($oldImage);
            }

            // Atualiza banco
            $this->foundProduct->update([
                'image_nutritional' => $path
            ]);

            Notification::make()->title('Salvo com sucesso!')->success()->send();
            
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
        $this->photo = null; // Limpa o arquivo temporário
        $this->data = []; 
        $this->dispatch('reset-scanner'); 
    }
}