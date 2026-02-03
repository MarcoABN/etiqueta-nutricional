<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\On; // Importante para ouvir eventos

class NutritionalScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Coletor Mobile';
    protected static ?string $title = 'Scanner';
    protected static string $view = 'filament.pages.nutritional-scanner';

    public $scannedCode = null;
    public $foundProduct = null;
    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Foto da Tabela')
                    ->heading('2. Capture a Tabela')
                    ->schema([
                        FileUpload::make('image_nutritional')
                            ->hiddenLabel()
                            ->image()
                            ->imageEditor()
                            ->imageEditorMode(2)
                            ->directory('uploads/nutritional')
                            ->required()
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'w-full']), // Força largura total
                    ])
            ])
            ->statePath('data');
    }

    public function handleBarcodeScan($code)
    {
        // Evita processar se já tiver encontrado (evita loops do scanner)
        if ($this->foundProduct) return;

        $this->scannedCode = $code;
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            $this->form->fill([
                'image_nutritional' => $product->image_nutritional,
            ]);
            Notification::make()->title("Produto: {$product->product_name}")->success()->send();
        } else {
            // Produto não encontrado: Avisa e reinicia scanner após 2s
            Notification::make()->title("EAN {$code} não encontrado!")->danger()->send();
            $this->dispatch('resume-camera'); // Manda o JS continuar lendo
        }
    }

    public function save()
    {
        $data = $this->form->getState();

        if ($this->foundProduct) {
            $this->foundProduct->update([
                'image_nutritional' => $data['image_nutritional']
            ]);

            Notification::make()->title('Salvo! Próximo...')->success()->send();
            $this->resetScanner();
        }
    }

    #[On('reset-action')] 
    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->form->fill();
        $this->dispatch('start-camera'); // Ordem explícita para o JS
    }
}