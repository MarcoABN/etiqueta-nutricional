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
                    
                    // --- OTIMIZAÇÃO DE EDITOR (CROP) ---
                    ->imageEditor()
                    ->imageEditorMode(2) // Modo 2 = Modal (Tela cheia no mobile)
                    
                    // 1. Define que não há proporção inicial forçada
                    ->imageCropAspectRatio(null) 
                    
                    // 2. Define explicitamente as opções disponíveis no rodapé do editor.
                    // O 'null' é o segredo: ele habilita o botão "Custom/Livre"
                    ->imageEditorAspectRatios([
                        null,   // Ícone de corte livre (Free)
                        '16:9',
                        '4:3',
                        '1:1',
                    ])
                    
                    // --- OTIMIZAÇÃO DE ARQUIVO ---
                    // Removemos resize na entrada para manter qualidade máxima p/ crop
                    ->imageResizeTargetWidth('2000') // Resize apenas na saída final
                    ->imageResizeTargetHeight('2000')
                    ->directory('uploads/nutritional')
                    ->disk('public')
                    
                    // Configuração para abrir câmera traseira (mobile)
                    ->extraInputAttributes([
                        'capture' => 'environment',
                        'accept' => 'image/*'
                    ])
                    ->statePath('image_nutritional'),
            ])
            ->statePath('data');
    }

    /**
     * Ação manual disparada pelo botão "Confirmar e Processar IA"
     */
    public function processImage()
    {
        $state = $this->form->getState();

        // Validação simples
        if (!$this->foundProduct) {
            Notification::make()->title('Nenhum produto selecionado.')->danger()->send();
            return;
        }

        if (empty($state['image_nutritional'])) {
            Notification::make()->title('Por favor, tire a foto da tabela.')->warning()->send();
            return;
        }

        // 1. Salva a imagem recortada no banco
        $this->foundProduct->update([
            'image_nutritional' => $state['image_nutritional']
        ]);

        // 2. Dispara o Job para a IA (Ollama)
        // Isso roda em background para não travar a tela
        ProcessProductImage::dispatch($this->foundProduct);

        Notification::make()
            ->title('Imagem enviada! A IA está lendo a tabela...')
            ->success()
            ->send();

        // 3. Limpa tudo para o próximo produto
        $this->resetScanner();
    }

    /**
     * Chamado via Javascript quando o QR Code é lido
     */
    public function handleBarcodeScan($code)
    {
        $this->scannedCode = $code;
        
        // Busca o produto pelo código de barras
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $this->foundProduct = $product;
            // Limpa o campo de imagem para evitar sujeira de leitura anterior
            $this->form->fill(['image_nutritional' => null]); 
        } else {
            $this->foundProduct = null;
            
            Notification::make()
                ->title("EAN {$code} não encontrado no cadastro.")
                ->danger()
                ->send();
            
            // Reinicia o scanner para tentar ler outro
            $this->dispatch('reset-scanner');
        }
    }

    /**
     * Reseta o estado da tela
     */
    public function resetScanner()
    {
        $this->scannedCode = null;
        $this->foundProduct = null;
        $this->data = [];
        $this->form->fill(); // Limpa o formulário visualmente
        
        // Emite evento para o Blade reiniciar a câmera JS
        $this->dispatch('reset-scanner'); 
    }
}