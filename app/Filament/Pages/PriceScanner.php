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

    // Propriedades de Estado
    public $filialId = null;
    public $product = null;     // Produto encontrado
    public $novoPreco = null;   // Input do usuário

    // Listeners
    protected $listeners = [
        'barcode-scanned' => 'handleBarcodeScan',
        'save-confirmed'  => 'forceSavePrice',
        'change-filial'   => 'changeFilial'
    ];

    public function mount()
    {
        // Recupera filial da sessão para não pedir toda hora
        if (Session::has('scanner_filial_id')) {
            $this->filialId = Session::get('scanner_filial_id');
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('filialId')
                    ->label('Selecione a Filial')
                    ->options(PctabprTn::distinct()->pluck('CODFILIAL', 'CODFILIAL'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        Session::put('scanner_filial_id', $state);
                        // Dispara evento para o JS iniciar a câmera assim que selecionar
                        $this->dispatch('filial-selected');
                    }),
            ]);
    }

    public function handleBarcodeScan($code)
    {
        if (!$this->filialId) return;

        // Busca o produto
        $found = PctabprTn::where('CODFILIAL', $this->filialId)
            ->where('CODAUXILIAR', $code)
            ->first();

        if ($found) {
            $this->product = $found;
            $this->novoPreco = $found->PVENDA_NOVO;
            
            // Avisa o Front para pausar câmera e mostrar modal
            $this->dispatch('product-found');
        } else {
            Notification::make()
                ->title('Produto não encontrado')
                ->body("EAN: $code")
                ->danger()
                ->duration(2000)
                ->send();
            
            // Manda reiniciar o scanner imediatamente
            $this->dispatch('reset-scanner');
        }
    }

    public function savePrice()
    {
        if (!$this->product) return;

        $valorLimpo = str_replace(',', '.', $this->novoPreco);

        // Se estiver vazio, salva como null (remove o preço novo)
        if ($valorLimpo === '' || $valorLimpo === null) {
             $this->forceSavePrice(null);
             return;
        }

        if (!is_numeric($valorLimpo)) {
            Notification::make()->title('Valor inválido')->warning()->send();
            return;
        }

        // Validação de Custo
        if ($valorLimpo < $this->product->CUSTOULTENT) {
            Notification::make()
                ->warning()
                ->title('Atenção: Prejuízo!')
                ->body("Custo: {$this->product->CUSTOULTENT} | Novo: $valorLimpo. Confirmar?")
                ->persistent()
                ->actions([
                    NotificationAction::make('confirmar')
                        ->label('Sim, Salvar')
                        ->button()
                        ->color('danger')
                        ->dispatch('save-confirmed'),
                    NotificationAction::make('cancelar')
                        ->label('Corrigir')
                        ->close(),
                ])
                ->send();
            return;
        }

        $this->forceSavePrice($valorLimpo);
    }

    public function forceSavePrice($valor = null)
    {
        if ($valor === null && $this->novoPreco !== null) {
            $valor = str_replace(',', '.', $this->novoPreco);
        }

        $this->product->PVENDA_NOVO = $valor;
        $this->product->save();

        Notification::make()->title('Preço salvo!')->success()->duration(1500)->send();

        $this->resetCycle();
    }

    public function resetCycle()
    {
        $this->product = null;
        $this->novoPreco = null;
        
        // O segredo: Dispara evento para o JS reabrir a câmera
        $this->dispatch('reset-scanner');
    }

    public function changeFilial()
    {
        $this->filialId = null;
        $this->product = null;
        Session::forget('scanner_filial_id');
        // O front irá detectar que filialId é null e mostrará o form
    }
}