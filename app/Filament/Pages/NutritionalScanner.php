<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Jobs\ProcessProductImage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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
                    ->label('Foto da Tabela')
                    ->image()
                    
                    // --- CONFIGURAÇÃO DE CORTE LIVRE (FREE CROP) ---
                    ->imageEditor()
                    ->imageEditorMode(2) // Modo Modal (Tela Cheia)
                    
                    // 1. Define que a proporção inicial é livre
                    ->imageCropAspectRatio(null)
                    
                    // 2. FORÇA O MODO LIVRE:
                    // Passando apenas [null], removemos as opções pré-definidas (1:1, 16:9).
                    // Isso obriga o editor a mostrar apenas a opção "Custom/Livre".
                    ->imageEditorAspectRatios([
                        null, 
                    ])
                    
                    // --- Otimização de Qualidade ---
                    // Alvo alto (2000px) para garantir leitura do OCR após o crop
                    ->imageResizeTargetWidth('2000')
                    ->imageResizeTargetHeight('2000')
                    
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    
                    // Configurações para mobile (Câmera Traseira)
                    ->extraInputAttributes([
                        'capture' => 'environment',
                        'accept' => 'image/*'
                    ])
                    ->statePath('image_nutritional'),
            ])
            ->statePath('data');
    }

    /**
     * Ação manual disparada pelo botão "Confirmar e Processar IA" na View
     */
    public function processImage()
    {
        $state = $this->form->getState();

        // Validações básicas
        if (!$this->foundProduct) {
            Notification::make()->title('Erro: Nenhum produto selecionado.')->danger()->send();
            return;
        }

        if (empty($state['image_nutritional'])) {
            Notification::make()->title('Atenção: Tire a foto da tabela antes de enviar.')->warning()->send();
            return;
        }

        // 1. Salva a imagem (já recortada) no banco de dados
        $this->foundProduct->update([
            'image_nutritional' => $state['image_nutritional']
        ]);

        // 2. Dispara o Job de Inteligência Artificial
        // O Job vai ler a imagem otimizada e traduzir/extrair dados
        ProcessProductImage::dispatch($this->foundProduct);

        Notification::make()
            ->title('Imagem enviada! A IA está processando...')
            ->success()
            ->send();

        // 3. Reinicia o scanner para o próximo produto
        $this->resetScanner();
    }

    /**
     * Chamado via Javascript quando o código de barras é lido pela câmera
     */
    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        
        // Tenta encontrar o produto
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            // Limpa o campo de imagem anterior para não confundir o operador
            $this->form->fill(['image_nutritional' => null]); 
        } else {
            $this->foundProduct = null;
            
            Notification::make()
                ->title("EAN {$code} não encontrado.")
                ->danger()
                ->send();
            
            // Reinicia a câmera imediatamente
            $this->dispatch('reset-scanner');
        }
    }

    /**
     * Reseta todo o estado da página
     */
    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->data = [];
        $this->form->fill(); // Esvazia o formulário visualmente
        
        // Emite evento para o Blade reiniciar a câmera JS
        $this->dispatch('reset-scanner'); 
    }
}