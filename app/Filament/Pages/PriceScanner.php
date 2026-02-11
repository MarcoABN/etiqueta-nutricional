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

    // Propriedades públicas
    public ?string $filialId = null;
    public ?object $product = null; 
    public ?string $novoPreco = null;

    // Listeners para comunicação com Javascript
    protected $listeners = [
        'barcode-scanned' => 'handleBarcodeScan',
        'save-confirmed'  => 'forceSavePrice'
    ];

    public function mount(): void
    {
        // Carrega filial da sessão se existir
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
                    ->placeholder('Escolha uma filial...')
                    ->options(function () {
                        return PctabprTn::query()
                            ->distinct()
                            ->orderBy('CODFILIAL')
                            ->pluck('CODFILIAL', 'CODFILIAL');
                    })
                    ->required()
                    ->searchable()
                    ->native(false),
            ])
            ->statePath('data');
    }

    /**
     * Inicia o scanner após seleção da filial
     */
    public function startScanner(): void
    {
        // Obtém os dados do formulário
        $formData = $this->form->getState();
        $filialSelecionada = $formData['filialId'] ?? null;

        // Valida se a filial foi selecionada
        if (!$filialSelecionada) {
            Notification::make()
                ->title('Selecione uma Filial')
                ->body('Por favor, escolha uma filial antes de iniciar o scanner.')
                ->warning()
                ->send();
            return;
        }

        // Salva na propriedade e na sessão
        $this->filialId = $filialSelecionada;
        Session::put('scanner_filial_id', $filialSelecionada);
        
        // Dispara evento para o frontend iniciar o scanner
        $this->dispatch('filial-selected');
        
        Notification::make()
            ->title('Scanner Iniciado!')
            ->body("Filial {$filialSelecionada} ativa. Aponte a câmera para o código de barras.")
            ->success()
            ->duration(3000)
            ->send();
    }

    /**
     * Processa código de barras lido pelo scanner
     */
    public function handleBarcodeScan(string $code): void
    {
        // Validação de filial
        if (!$this->filialId) {
            Notification::make()
                ->title('Erro: Filial não selecionada')
                ->danger()
                ->send();
            return;
        }

        // Remove espaços e caracteres inválidos
        $code = trim($code);

        // Busca o produto
        $found = PctabprTn::where('CODFILIAL', $this->filialId)
            ->where('CODAUXILIAR', $code)
            ->first();

        if ($found) {
            $this->product = $found;
            // Preenche com o preço novo se existir, senão com o preço atual
            $this->novoPreco = $found->PVENDA_NOVO 
                ? number_format($found->PVENDA_NOVO, 2, ',', '.')
                : number_format($found->PVENDA, 2, ',', '.');
            
            // Notifica o frontend
            $this->dispatch('product-found');
            
            Notification::make()
                ->title('Produto Encontrado!')
                ->body($found->DESCRICAO)
                ->success()
                ->duration(1500)
                ->send();
        } else {
            // Produto não encontrado
            Notification::make()
                ->title('Produto não encontrado')
                ->body("Código: {$code}")
                ->warning()
                ->duration(2000)
                ->send();
            
            // Mantém o scanner ativo
            $this->dispatch('reset-scanner');
        }
    }

    /**
     * Salva o novo preço com validações
     */
    public function savePrice(): void
    {
        if (!$this->product) {
            Notification::make()
                ->title('Erro: Nenhum produto selecionado')
                ->danger()
                ->send();
            return;
        }

        // Limpa e converte o valor
        $valorLimpo = str_replace(['.', ','], ['', '.'], trim($this->novoPreco));
        $valorFloat = floatval($valorLimpo);

        // Validação: preço vazio ou zero
        if (empty($this->novoPreco) || $valorFloat <= 0) {
            Notification::make()
                ->title('Valor Inválido')
                ->body('Informe um preço válido maior que zero.')
                ->warning()
                ->send();
            return;
        }

        // Validação: preço abaixo do custo (requer confirmação)
        if ($valorFloat < $this->product->CUSTOULTENT) {
            Notification::make()
                ->warning()
                ->title('⚠️ Atenção: Preço Abaixo do Custo!')
                ->body(sprintf(
                    'Custo: R$ %s | Novo preço: R$ %s',
                    number_format($this->product->CUSTOULTENT, 2, ',', '.'),
                    number_format($valorFloat, 2, ',', '.')
                ))
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('confirmar')
                        ->label('Confirmar')
                        ->button()
                        ->color('danger')
                        ->dispatch('save-confirmed'),
                    \Filament\Notifications\Actions\Action::make('cancelar')
                        ->label('Cancelar')
                        ->close(),
                ])
                ->send();
            return;
        }

        // Se passou nas validações, salva
        $this->forceSavePrice();
    }

    /**
     * Força o salvamento (usado após confirmação de preço abaixo do custo)
     */
    public function forceSavePrice(): void
    {
        if (!$this->product) return;

        try {
            // Converte o valor
            $valorLimpo = str_replace(['.', ','], ['', '.'], trim($this->novoPreco));
            $valorFloat = floatval($valorLimpo);

            // Atualiza o preço
            $this->product->PVENDA_NOVO = $valorFloat;
            $this->product->save();

            // Notificação de sucesso
            Notification::make()
                ->title('✅ Preço Salvo com Sucesso!')
                ->body(sprintf(
                    '%s - R$ %s',
                    $this->product->DESCRICAO,
                    number_format($valorFloat, 2, ',', '.')
                ))
                ->success()
                ->duration(2000)
                ->send();

            // Limpa os dados do produto
            $this->resetCycle();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erro ao Salvar')
                ->body('Ocorreu um erro: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Cancela a edição e volta para o scanner
     */
    public function cancelEdit(): void
    {
        Notification::make()
            ->title('Edição Cancelada')
            ->body('Voltando ao scanner...')
            ->info()
            ->duration(1500)
            ->send();
        
        $this->resetCycle();
    }

    /**
     * Reseta o ciclo de edição e volta ao scanner
     */
    private function resetCycle(): void
    {
        // Limpa os dados do produto
        $this->product = null;
        $this->novoPreco = null;
        
        // Dispara evento para reativar o scanner
        $this->dispatch('reset-scanner');
    }

    /**
     * Muda a filial (volta para tela de seleção)
     */
    public function changeFilial(): void
    {
        // Limpa tudo
        $this->filialId = null;
        $this->product = null;
        $this->novoPreco = null;
        
        // Remove da sessão
        Session::forget('scanner_filial_id');
        
        Notification::make()
            ->title('Filial Desconectada')
            ->body('Selecione uma nova filial para continuar.')
            ->info()
            ->send();
    }

    /**
     * Retorna os dados do formulário
     */
    protected function getFormStatePath(): ?string
    {
        return 'data';
    }
}