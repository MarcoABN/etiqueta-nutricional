<?php

namespace App\Filament\Pages;

use App\Models\PctabprTn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
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

    // Propriedades
    public $filialId = null;
    public $product = null; 
    public $novoPreco = null;

    // Listeners para comunicação com o Javascript
    protected $listeners = [
        'barcode-scanned' => 'handleBarcodeScan', // Chamado pelo JS quando lê código
        'save-confirmed'  => 'forceSavePrice'     // Chamado se confirmar alerta de custo
    ];

    public function mount()
    {
        // Se já tiver filial na sessão, carrega direto
        if (Session::has('scanner_filial_id')) {
            $this->filialId = Session::get('scanner_filial_id');
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('filialId')
                    ->label('Filial de Operação')
                    ->options(PctabprTn::distinct()->pluck('CODFILIAL', 'CODFILIAL'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        Session::put('scanner_filial_id', $state);
                        // Avisa o front para ligar a câmera
                        $this->dispatch('filial-selected');
                    }),
            ]);
    }

    public function handleBarcodeScan($code)
    {
        if (!$this->filialId) return;

        $found = PctabprTn::where('CODFILIAL', $this->filialId)
            ->where('CODAUXILIAR', $code)
            ->first();

        if ($found) {
            $this->product = $found;
            $this->novoPreco = $found->PVENDA_NOVO;
            
            // Avisa o front: "Pare a câmera, toque o beep e mostre o modal"
            $this->dispatch('product-found');
        } else {
            Notification::make()->title("Não encontrado: $code")->danger()->duration(1500)->send();
            // Avisa o front: "Continue lendo"
            $this->dispatch('reset-scanner');
        }
    }

    public function savePrice()
    {
        if (!$this->product) return;

        $valorLimpo = str_replace(',', '.', $this->novoPreco);

        // Validação de segurança (Custo)
        if ($valorLimpo > 0 && $valorLimpo < $this->product->CUSTOULTENT) {
            $this->dispatch('cost-alert'); // Apenas um aviso visual extra se quiser
            Notification::make()
                ->warning()
                ->title('Atenção: Abaixo do Custo!')
                ->body('Confirma o prejuízo?')
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('sim')->label('Sim')->button()->color('danger')->dispatch('save-confirmed'),
                    \Filament\Notifications\Actions\Action::make('nao')->label('Cancelar')->close(),
                ])
                ->send();
            return;
        }

        $this->forceSavePrice();
    }

    public function forceSavePrice()
    {
        $valor = str_replace(',', '.', $this->novoPreco);
        $this->product->PVENDA_NOVO = ($valor == '') ? null : $valor;
        $this->product->save();

        Notification::make()->title('Preço Salvo!')->success()->duration(1000)->send();

        // Limpa tudo e manda o front reabrir a câmera
        $this->product = null;
        $this->novoPreco = null;
        $this->dispatch('reset-scanner');
    }

    public function changeFilial()
    {
        $this->filialId = null;
        $this->product = null;
        Session::forget('scanner_filial_id');
    }
}