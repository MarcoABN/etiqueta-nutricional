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

    // Propriedades
    public $filialId = null;
    public $showScanner = false;
    public $product = null;
    public $novoPreco = null;

    // Ouvintes de eventos do Front (JS) e Notificações
    protected $listeners = [
        'barcode-scanned' => 'handleBarcodeScan',
        'save-confirmed'  => 'forceSavePrice'
    ];

    public function mount()
    {
        // Recupera filial da sessão se existir
        if (Session::has('scanner_filial_id')) {
            $this->filialId = Session::get('scanner_filial_id');
            $this->showScanner = true;
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('filialId')
                    ->label('Selecione a Filial de Operação')
                    ->options(PctabprTn::distinct()->pluck('CODFILIAL', 'CODFILIAL'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        Session::put('scanner_filial_id', $state);
                        $this->showScanner = true;
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
            
            // Toca o beep e foca no campo
            $this->dispatch('play-beep');
            $this->dispatch('focus-price');
        } else {
            Notification::make()
                ->title('Produto não encontrado')
                ->body("EAN: $code na Filial {$this->filialId}")
                ->danger()
                ->duration(3000)
                ->send();
            
            // Reinicia o scanner no JS
            $this->dispatch('reset-scanner');
        }
    }

    public function savePrice()
    {
        if (!$this->product) return;

        // Tratamento de vírgula/ponto
        $valorLimpo = str_replace(',', '.', $this->novoPreco);

        if ($valorLimpo == '') {
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
        // Se foi chamado pelo listener 'save-confirmed', pega o valor do estado
        if ($valor === null && $this->novoPreco !== null) {
            $valor = str_replace(',', '.', $this->novoPreco);
        }

        $this->product->PVENDA_NOVO = $valor;
        $this->product->save();

        Notification::make()->title('Preço atualizado!')->success()->duration(1500)->send();

        // Reseta para ler o próximo
        $this->resetCycle();
    }

    public function resetCycle()
    {
        $this->product = null;
        $this->novoPreco = null;
        $this->dispatch('reset-scanner'); // Manda o JS reabrir a câmera
    }

    public function changeFilial()
    {
        $this->showScanner = false;
        $this->filialId = null;
        $this->product = null;
        Session::forget('scanner_filial_id');
    }
}