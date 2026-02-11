<?php

namespace App\Filament\Pages;

use App\Models\PctabprTn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Session;

class PriceScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Scanner de Preços';
    protected static ?string $title = 'Coletor de Preços';
    protected static string $view = 'filament.pages.price-scanner';

    protected static ?string $navigationGroup = 'Precificação';

    // Propriedades (Estado)
    public $selectedFilial = null;
    public $scannedCode = null; // Código lido
    public $foundProduct = null; // Objeto do produto
    public $novoPreco = null;    // Campo de input

    // Listeners para eventos do Front e Back
    protected $listeners = [
        'barcode-scanned' => 'handleBarcodeScan', // Vem do JS
        'save-confirmed'  => 'forceSavePrice'     // Vem da Notificação de Confirmação
    ];

    public function mount()
    {
        // Recupera a filial da sessão se o usuário der F5
        if (Session::has('scanner_filial')) {
            $this->selectedFilial = Session::get('scanner_filial');
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedFilial')
                    ->label('Filial de Operação')
                    ->options(PctabprTn::distinct()->pluck('CODFILIAL', 'CODFILIAL'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        Session::put('scanner_filial', $state);
                    }),
            ]);
    }

    public function handleBarcodeScan($code)
    {
        if (!$this->selectedFilial) {
            Notification::make()->title('Selecione a filial primeiro!')->warning()->send();
            return;
        }

        $this->scannedCode = $code;
        
        // Busca produto na filial selecionada
        $product = PctabprTn::where('CODAUXILIAR', $code)
            ->where('CODFILIAL', $this->selectedFilial)
            ->first();

        if ($product) {
            $this->foundProduct = $product;
            $this->novoPreco = $product->PVENDA_NOVO; 
            
            // Opcional: Tocar um som de "beep" via JS se desejar
            $this->dispatch('play-beep'); 
        } else {
            // Se não achar, avisa e JÁ REINICIA o scanner para o próximo
            Notification::make()
                ->title('Produto não encontrado')
                ->body("EAN: $code na Filial {$this->selectedFilial}")
                ->danger()
                ->duration(3000)
                ->send();
            
            $this->dispatch('reset-scanner-ui');
        }
    }

    public function savePrice()
    {
        if (!$this->foundProduct) return;

        $valorLimpo = str_replace(',', '.', $this->novoPreco);

        // Validação de numérico
        if (!is_numeric($valorLimpo) && !empty($this->novoPreco)) {
             Notification::make()->title('Valor inválido')->warning()->send();
             return;
        }

        // --- REGRA DE NEGÓCIO: PREÇO ABAIXO DO CUSTO ---
        $custo = $this->foundProduct->CUSTOULTENT;

        if ($valorLimpo > 0 && $valorLimpo < $custo) {
            Notification::make()
                ->warning()
                ->title('Atenção: Prejuízo Detectado!')
                ->body("Custo: R$ " . number_format($custo, 2, ',', '.') . " | Novo: R$ " . number_format($valorLimpo, 2, ',', '.') . "\nDeseja confirmar?")
                ->persistent()
                ->actions([
                    NotificationAction::make('confirmar')
                        ->label('Sim, Confirmar')
                        ->button()
                        ->color('danger')
                        ->dispatch('save-confirmed'), // Chama forceSavePrice
                    NotificationAction::make('cancelar')
                        ->label('Corrigir')
                        ->color('gray')
                        ->close(),
                ])
                ->send();
            return; 
        }

        // Se estiver tudo ok, salva direto
        $this->forceSavePrice();
    }

    public function forceSavePrice()
    {
        $valorLimpo = str_replace(',', '.', $this->novoPreco);
        
        $this->foundProduct->PVENDA_NOVO = empty($this->novoPreco) ? null : $valorLimpo;
        $this->foundProduct->save();

        Notification::make()
            ->title('Salvo!')
            ->success()
            ->duration(1500) // Duração curta para agilizar
            ->send();

        // AQUI ESTÁ A MÁGICA: Limpa o produto mas mantém a filial
        $this->resetProductState();
    }

    public function resetProductState()
    {
        $this->foundProduct = null;
        $this->scannedCode = null;
        $this->novoPreco = null;
        
        // Este evento diz ao Front: "Ocultei o formulário, mostre a câmera de novo"
        $this->dispatch('reset-scanner-ui'); 
    }

    public function changeFilial()
    {
        $this->selectedFilial = null;
        $this->foundProduct = null;
        Session::forget('scanner_filial');
    }
}